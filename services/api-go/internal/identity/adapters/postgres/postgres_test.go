package postgres_test

import (
	"context"
	"errors"
	"testing"
	"time"

	"github.com/google/uuid"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	platformpg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

var now = time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC)

type repos struct {
	tx     *platformpg.Transactor
	users  *postgres.Users
	tokens *postgres.RefreshTokens
}

func newRepos(t *testing.T) repos {
	t.Helper()

	tx := platformpg.NewTransactor(testdb.New(t))

	return repos{tx: tx, users: postgres.NewUsers(tx), tokens: postgres.NewRefreshTokens(tx)}
}

func newUser(email string) ports.NewUser {
	return ports.NewUser{Name: "Ada", Email: email, PasswordHash: "not-a-bcrypt-hash"}
}

// The harness smoke test from story 2: repository calls inside InTx roll
// back with it.
func TestRepositoriesRollBackWithTheTransaction(t *testing.T) {
	t.Parallel()

	r := newRepos(t)
	ctx := t.Context()
	errForced := errors.New("forced")

	err := r.tx.InTx(ctx, func(ctx context.Context) error {
		user, err := r.users.Create(ctx, newUser("ada@example.com"))
		if err != nil {
			return err
		}

		if user.ID == 0 || user.Locale != "en" || user.ThemePreference != "system" || user.IsVerified() {
			t.Errorf("Create() = %+v, want an id and the column defaults", user)
		}

		return errForced
	})
	if !errors.Is(err, errForced) {
		t.Fatalf("InTx() error = %v, want the forced error", err)
	}

	if _, err := r.users.ByEmail(ctx, "ada@example.com"); !apperr.HasKind(err, apperr.KindNotFound) {
		t.Errorf("ByEmail() after rollback error = %v, want not found", err)
	}
}

func TestEmailIsUniqueIgnoringCase(t *testing.T) {
	t.Parallel()

	r := newRepos(t)
	ctx := t.Context()

	created, err := r.users.Create(ctx, newUser("Ada@Example.com"))
	if err != nil {
		t.Fatal(err)
	}

	found, err := r.users.ByEmail(ctx, "ada@example.COM")
	if err != nil || found.ID != created.ID {
		t.Errorf("ByEmail() = %+v, %v; want user %d", found, err, created.ID)
	}

	_, err = r.users.Create(ctx, newUser("ada@example.com"))
	if !r.users.IsDuplicateEmail(err) {
		t.Errorf("duplicate Create() error = %v, want a duplicate email", err)
	}

	if r.users.IsDuplicateEmail(errors.New("other")) {
		t.Error("IsDuplicateEmail(other error) = true")
	}
}

func TestRolesAndVerifiedUpsert(t *testing.T) {
	t.Parallel()

	r := newRepos(t)
	ctx := t.Context()

	user, err := r.users.UpsertVerified(ctx, newUser("admin@example.com"), now)
	if err != nil {
		t.Fatal(err)
	}

	for range 2 {
		if err := r.users.GrantRole(ctx, user.ID, domain.RoleAdmin); err != nil {
			t.Fatalf("GrantRole() error = %v", err)
		}
	}

	again, err := r.users.UpsertVerified(ctx, ports.NewUser{Name: "Renamed", Email: "ADMIN@example.com", PasswordHash: "h2"}, now)
	if err != nil {
		t.Fatal(err)
	}

	got, err := r.users.ByID(ctx, user.ID)
	if err != nil {
		t.Fatal(err)
	}

	if again.ID != user.ID || got.Name != "Renamed" || got.PasswordHash != "h2" || !got.IsAdmin || !got.IsVerified() {
		t.Errorf("after upsert and grant: %+v, want the same verified admin, renamed", got)
	}

	if _, err := r.users.ByID(ctx, 999); !apperr.HasKind(err, apperr.KindNotFound) {
		t.Errorf("ByID(missing) error = %v, want not found", err)
	}
}

func TestRefreshTokenFamilies(t *testing.T) {
	t.Parallel()

	r := newRepos(t)
	ctx := t.Context()

	user, err := r.users.Create(ctx, newUser("ada@example.com"))
	if err != nil {
		t.Fatal(err)
	}

	family, other := uuid.NewString(), uuid.NewString()
	device := "iPhone"

	create := func(familyID, hash string) domain.RefreshToken {
		t.Helper()

		token, err := r.tokens.Create(ctx, domain.RefreshToken{
			UserID: user.ID, FamilyID: familyID, TokenHash: hash, DeviceName: &device,
			AuthenticatedAt: now, ExpiresAt: now.Add(time.Hour),
		}, now)
		if err != nil {
			t.Fatal(err)
		}

		return token
	}

	first, second, elsewhere := create(family, "h1"), create(family, "h2"), create(other, "h3")

	err = r.tx.InTx(ctx, func(ctx context.Context) error {
		locked, err := r.tokens.Lock(ctx, first.ID)
		if err != nil {
			return err
		}

		return r.tokens.MarkReplaced(ctx, locked.ID, second.ID, now)
	})
	if err != nil {
		t.Fatal(err)
	}

	rotated, err := r.tokens.ByHash(ctx, "h1")
	if err != nil || !rotated.IsRotated() || !rotated.IsRevoked() || *rotated.ReplacedByID != second.ID || *rotated.DeviceName != "iPhone" {
		t.Errorf("rotated = %+v, %v", rotated, err)
	}

	stepped := now.Add(10 * time.Minute)
	if err := r.tokens.StepUp(ctx, family, user.ID, stepped); err != nil {
		t.Fatal(err)
	}

	if live, _ := r.tokens.ByHash(ctx, "h2"); !live.AuthenticatedAt.Equal(stepped) {
		t.Errorf("live token authenticated_at = %v, want the step-up time", live.AuthenticatedAt)
	}

	if old, _ := r.tokens.ByHash(ctx, "h1"); !old.AuthenticatedAt.Equal(now) {
		t.Errorf("revoked token authenticated_at = %v, want it unchanged", old.AuthenticatedAt)
	}

	if err := r.tokens.RevokeFamily(ctx, family, now); err != nil {
		t.Fatal(err)
	}

	if t2, _ := r.tokens.ByHash(ctx, "h2"); !t2.IsRevoked() {
		t.Error("the family's live token is not revoked")
	}

	if t3, _ := r.tokens.ByHash(ctx, "h3"); t3.IsRevoked() || t3.FamilyID != elsewhere.FamilyID {
		t.Error("another family was revoked")
	}

	if _, err := r.tokens.ByHash(ctx, "missing"); !apperr.HasKind(err, apperr.KindNotFound) {
		t.Errorf("ByHash(missing) error = %v, want not found", err)
	}

	for _, bad := range []error{
		r.tokens.RevokeFamily(ctx, "not-a-uuid", now),
		r.tokens.StepUp(ctx, "not-a-uuid", user.ID, now),
	} {
		if bad == nil {
			t.Error("a malformed family id was accepted")
		}
	}
}

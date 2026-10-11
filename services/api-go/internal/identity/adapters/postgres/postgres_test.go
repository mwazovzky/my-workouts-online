package postgres_test

import (
	"context"
	"errors"
	"testing"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

// The harness smoke test: generated queries run inside InTx and roll back
// with it.
func TestGeneratedQueriesRollBackWithTheTransaction(t *testing.T) {
	t.Parallel()

	tx := postgres.NewTransactor(testdb.New(t))
	ctx := t.Context()
	errForced := errors.New("forced")

	err := tx.InTx(ctx, func(ctx context.Context) error {
		user, err := sqlcgen.New(tx.Querier(ctx)).CreateUser(ctx, newUser("ada@example.com"))
		if err != nil {
			return err
		}

		if user.ID == 0 || user.Locale != "en" || user.ThemePreference != "system" || user.EmailVerifiedAt != nil {
			t.Errorf("CreateUser() = %+v, want an id and the column defaults", user)
		}

		return errForced
	})
	if !errors.Is(err, errForced) {
		t.Fatalf("InTx() error = %v, want the forced error", err)
	}

	_, err = sqlcgen.New(tx.Querier(ctx)).GetUserByEmail(ctx, "ada@example.com")
	if !apperr.HasKind(postgres.MapError(err), apperr.KindNotFound) {
		t.Errorf("GetUserByEmail() after rollback error = %v, want not found", err)
	}
}

func TestEmailIsUniqueIgnoringCase(t *testing.T) {
	t.Parallel()

	queries := sqlcgen.New(testdb.New(t))
	ctx := t.Context()

	created, err := queries.CreateUser(ctx, newUser("Ada@Example.com"))
	if err != nil {
		t.Fatal(err)
	}

	found, err := queries.GetUserByEmail(ctx, "ada@example.COM")
	if err != nil || found.ID != created.ID {
		t.Errorf("GetUserByEmail() = %+v, %v; want user %d", found, err, created.ID)
	}

	_, err = queries.CreateUser(ctx, newUser("ada@example.com"))

	mapped := postgres.MapError(err)
	if !apperr.HasKind(mapped, apperr.KindConflict) || postgres.ConstraintName(mapped) != "users_email_key" {
		t.Errorf("duplicate CreateUser() error = %v, want a conflict on users_email_key", mapped)
	}
}

func newUser(email string) sqlcgen.CreateUserParams {
	return sqlcgen.CreateUserParams{Name: "Ada", Email: email, PasswordHash: "not-a-bcrypt-hash"} //nolint:gosec // G101: a placeholder, not a credential
}

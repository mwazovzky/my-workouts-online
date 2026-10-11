package postgres

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// uniqueEmail is the case-insensitive unique index on users.email.
const uniqueEmail = "users_email_key"

// Users implements ports.Users.
type Users struct {
	tx *postgres.Transactor
}

var _ ports.Users = (*Users)(nil)

// NewUsers returns the Users repository.
func NewUsers(tx *postgres.Transactor) *Users {
	return &Users{tx: tx}
}

func (r *Users) q(ctx context.Context) *sqlcgen.Queries {
	return sqlcgen.New(r.tx.Querier(ctx))
}

// Create implements ports.Users.
func (r *Users) Create(ctx context.Context, u ports.NewUser) (domain.User, error) {
	row, err := r.q(ctx).CreateUser(ctx, sqlcgen.CreateUserParams{Name: u.Name, Email: u.Email, PasswordHash: u.PasswordHash})
	if err != nil {
		return domain.User{}, postgres.MapError(err)
	}

	return toUser(row, false), nil
}

// UpsertVerified implements ports.Users.
func (r *Users) UpsertVerified(ctx context.Context, u ports.NewUser, verifiedAt time.Time) (domain.User, error) {
	row, err := r.q(ctx).UpsertVerifiedUser(ctx, sqlcgen.UpsertVerifiedUserParams{
		Name: u.Name, Email: u.Email, PasswordHash: u.PasswordHash, VerifiedAt: verifiedAt,
	})
	if err != nil {
		return domain.User{}, postgres.MapError(err)
	}

	return toUser(row, false), nil
}

// ByEmail implements ports.Users.
func (r *Users) ByEmail(ctx context.Context, email string) (domain.User, error) {
	row, err := r.q(ctx).GetUserByEmail(ctx, email)
	if err != nil {
		return domain.User{}, postgres.MapError(err)
	}

	return toUser(row.User, row.IsAdmin), nil
}

// ByID implements ports.Users.
func (r *Users) ByID(ctx context.Context, id int64) (domain.User, error) {
	row, err := r.q(ctx).GetUserByID(ctx, id)
	if err != nil {
		return domain.User{}, postgres.MapError(err)
	}

	return toUser(row.User, row.IsAdmin), nil
}

// GrantRole implements ports.Users.
func (r *Users) GrantRole(ctx context.Context, userID int64, role string) error {
	_, err := r.q(ctx).GrantRole(ctx, userID, role)

	return postgres.MapError(err)
}

// IsDuplicateEmail implements ports.Users.
func (r *Users) IsDuplicateEmail(err error) bool {
	return apperr.HasKind(err, apperr.KindConflict) && postgres.ConstraintName(err) == uniqueEmail
}

func toUser(u sqlcgen.User, isAdmin bool) domain.User {
	return domain.User{
		ID:              u.ID,
		Name:            u.Name,
		Email:           u.Email,
		EmailVerifiedAt: u.EmailVerifiedAt,
		PasswordHash:    u.PasswordHash,
		Locale:          u.Locale,
		ThemePreference: u.ThemePreference,
		IsAdmin:         isAdmin,
		CreatedAt:       u.CreatedAt,
		UpdatedAt:       u.UpdatedAt,
	}
}

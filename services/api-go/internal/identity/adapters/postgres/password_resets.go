package postgres

import (
	"context"
	"strings"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// PasswordResets implements ports.PasswordResets. Emails are stored
// lowercased, so a reset matches the account whatever the case typed.
type PasswordResets struct {
	tx *postgres.Transactor
}

var _ ports.PasswordResets = (*PasswordResets)(nil)

// NewPasswordResets returns the PasswordResets repository.
func NewPasswordResets(tx *postgres.Transactor) *PasswordResets {
	return &PasswordResets{tx: tx}
}

func (r *PasswordResets) q(ctx context.Context) *sqlcgen.Queries {
	return sqlcgen.New(r.tx.Querier(ctx))
}

// Put implements ports.PasswordResets.
func (r *PasswordResets) Put(ctx context.Context, reset domain.PasswordReset) error {
	return postgres.MapError(r.q(ctx).PutPasswordReset(ctx, sqlcgen.PutPasswordResetParams{
		Email: strings.ToLower(reset.Email), TokenHash: reset.TokenHash, CreatedAt: reset.CreatedAt,
	}))
}

// ByEmail implements ports.PasswordResets.
func (r *PasswordResets) ByEmail(ctx context.Context, email string) (domain.PasswordReset, error) {
	row, err := r.q(ctx).GetPasswordReset(ctx, strings.ToLower(email))
	if err != nil {
		return domain.PasswordReset{}, postgres.MapError(err)
	}

	return domain.PasswordReset{Email: row.Email, TokenHash: row.TokenHash, CreatedAt: row.CreatedAt}, nil
}

// Delete implements ports.PasswordResets.
func (r *PasswordResets) Delete(ctx context.Context, email string) error {
	return postgres.MapError(r.q(ctx).DeletePasswordReset(ctx, strings.ToLower(email)))
}

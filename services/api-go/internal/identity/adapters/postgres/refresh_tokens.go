package postgres

import (
	"context"
	"fmt"
	"time"

	"github.com/google/uuid"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/postgres/sqlcgen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// RefreshTokens implements ports.RefreshTokens.
type RefreshTokens struct {
	tx *postgres.Transactor
}

var _ ports.RefreshTokens = (*RefreshTokens)(nil)

// NewRefreshTokens returns the RefreshTokens repository.
func NewRefreshTokens(tx *postgres.Transactor) *RefreshTokens {
	return &RefreshTokens{tx: tx}
}

func (r *RefreshTokens) q(ctx context.Context) *sqlcgen.Queries {
	return sqlcgen.New(r.tx.Querier(ctx))
}

// Create implements ports.RefreshTokens.
func (r *RefreshTokens) Create(ctx context.Context, t domain.RefreshToken, now time.Time) (domain.RefreshToken, error) {
	family, err := uuid.Parse(t.FamilyID)
	if err != nil {
		return domain.RefreshToken{}, fmt.Errorf("refresh token family: %w", err)
	}

	row, err := r.q(ctx).CreateRefreshToken(ctx, sqlcgen.CreateRefreshTokenParams{
		UserID:          t.UserID,
		FamilyID:        family,
		TokenHash:       t.TokenHash,
		DeviceName:      t.DeviceName,
		AuthenticatedAt: t.AuthenticatedAt,
		ExpiresAt:       t.ExpiresAt,
		Now:             now,
	})
	if err != nil {
		return domain.RefreshToken{}, postgres.MapError(err)
	}

	return toRefreshToken(row), nil
}

// ByHash implements ports.RefreshTokens.
func (r *RefreshTokens) ByHash(ctx context.Context, hash string) (domain.RefreshToken, error) {
	row, err := r.q(ctx).GetRefreshTokenByHash(ctx, hash)
	if err != nil {
		return domain.RefreshToken{}, postgres.MapError(err)
	}

	return toRefreshToken(row), nil
}

// Lock implements ports.RefreshTokens.
func (r *RefreshTokens) Lock(ctx context.Context, id int64) (domain.RefreshToken, error) {
	row, err := r.q(ctx).LockRefreshToken(ctx, id)
	if err != nil {
		return domain.RefreshToken{}, postgres.MapError(err)
	}

	return toRefreshToken(row), nil
}

// MarkReplaced implements ports.RefreshTokens.
func (r *RefreshTokens) MarkReplaced(ctx context.Context, id, replacedBy int64, now time.Time) error {
	return postgres.MapError(r.q(ctx).MarkRefreshTokenReplaced(ctx, sqlcgen.MarkRefreshTokenReplacedParams{
		ID: id, ReplacedByID: replacedBy, Now: now,
	}))
}

// RevokeFamily implements ports.RefreshTokens.
func (r *RefreshTokens) RevokeFamily(ctx context.Context, familyID string, now time.Time) error {
	family, err := uuid.Parse(familyID)
	if err != nil {
		return fmt.Errorf("refresh token family: %w", err)
	}

	return postgres.MapError(r.q(ctx).RevokeRefreshFamily(ctx, now, family))
}

// StepUp implements ports.RefreshTokens.
func (r *RefreshTokens) StepUp(ctx context.Context, familyID string, userID int64, now time.Time) error {
	family, err := uuid.Parse(familyID)
	if err != nil {
		return fmt.Errorf("refresh token family: %w", err)
	}

	return postgres.MapError(r.q(ctx).StepUpRefreshFamily(ctx, sqlcgen.StepUpRefreshFamilyParams{
		Now: now, FamilyID: family, UserID: userID,
	}))
}

func toRefreshToken(t sqlcgen.RefreshToken) domain.RefreshToken {
	return domain.RefreshToken{
		ID:              t.ID,
		UserID:          t.UserID,
		FamilyID:        t.FamilyID.String(),
		TokenHash:       t.TokenHash,
		DeviceName:      t.DeviceName,
		AuthenticatedAt: t.AuthenticatedAt,
		ExpiresAt:       t.ExpiresAt,
		RevokedAt:       t.RevokedAt,
		ReplacedByID:    t.ReplacedByID,
	}
}

// RevokeUserFamilies implements ports.RefreshTokens.
func (r *RefreshTokens) RevokeUserFamilies(ctx context.Context, userID int64, keepFamily string, now time.Time) error {
	params := sqlcgen.RevokeUserFamiliesParams{Now: now, UserID: userID}

	if keepFamily != "" {
		family, err := uuid.Parse(keepFamily)
		if err != nil {
			return fmt.Errorf("refresh token family: %w", err)
		}

		params.KeepFamily = &family
	}

	return postgres.MapError(r.q(ctx).RevokeUserFamilies(ctx, params))
}

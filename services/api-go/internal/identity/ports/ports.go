// Package ports declares what identity's use cases need from the outside.
package ports

import (
	"context"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
)

// NewUser is an account to create.
type NewUser struct {
	Name         string
	Email        string
	PasswordHash string
}

// Users stores accounts.
type Users interface {
	// Create inserts u; a duplicate email is an apperr Conflict that
	// IsDuplicateEmail recognises.
	Create(ctx context.Context, u NewUser) (domain.User, error)
	// UpsertVerified creates u or resets the account with u's email to u's
	// name and password, verified at verifiedAt.
	UpsertVerified(ctx context.Context, u NewUser, verifiedAt time.Time) (domain.User, error)
	// ByEmail finds an account ignoring case; apperr NotFound otherwise.
	ByEmail(ctx context.Context, email string) (domain.User, error)
	// ByID finds an account; apperr NotFound otherwise.
	ByID(ctx context.Context, id int64) (domain.User, error)
	// GrantRole gives the user the named role; granting it twice is a no-op.
	GrantRole(ctx context.Context, userID int64, role string) error
	// IsDuplicateEmail reports whether err is Create's duplicate-email conflict.
	IsDuplicateEmail(err error) bool
}

// RefreshTokens stores refresh-token families.
type RefreshTokens interface {
	Create(ctx context.Context, t domain.RefreshToken, now time.Time) (domain.RefreshToken, error)
	// ByHash finds a token by its hash; apperr NotFound otherwise.
	ByHash(ctx context.Context, hash string) (domain.RefreshToken, error)
	// Lock reads a token and locks it until the transaction ends.
	Lock(ctx context.Context, id int64) (domain.RefreshToken, error)
	// MarkReplaced revokes token id as rotated into replacedBy.
	MarkReplaced(ctx context.Context, id, replacedBy int64, now time.Time) error
	// RevokeFamily revokes every unrevoked token of the family.
	RevokeFamily(ctx context.Context, familyID string, now time.Time) error
	// StepUp moves the authentication time of the family's live tokens to now.
	StepUp(ctx context.Context, familyID string, userID int64, now time.Time) error
}

// PasswordHasher hashes and checks passwords.
type PasswordHasher interface {
	Hash(password string) (string, error)
	Matches(hash, password string) bool
}

// TokenSigner issues and verifies access tokens.
type TokenSigner interface {
	Sign(claims domain.AccessClaims) (string, error)
	// Verify checks the signature, issuer, audience and expiry (with
	// leeway); an expired token is domain.ErrAccessTokenExpired, any other
	// failure domain.ErrUnauthenticated.
	Verify(token string) (domain.AccessClaims, error)
}

// LoginAttempts counts failed logins per key in a window that opens at the
// first failure. platform/ratelimit.Memory implements it.
type LoginAttempts interface {
	Hits(ctx context.Context, key string) (hits int, resetIn time.Duration, err error)
	Hit(ctx context.Context, key string, window time.Duration) error
	Clear(ctx context.Context, key string) error
}

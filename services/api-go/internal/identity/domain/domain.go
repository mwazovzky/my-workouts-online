// Package domain holds identity's types and their invariants.
package domain

import (
	"crypto/sha256"
	"encoding/hex"
	"time"
)

// Role names the application checks.
const (
	RoleAdmin = "Admin"
	RoleUser  = "User"
)

// User is an account.
type User struct {
	ID              int64
	Name            string
	Email           string
	EmailVerifiedAt *time.Time
	PasswordHash    string
	Locale          string
	ThemePreference string
	IsAdmin         bool
	CreatedAt       time.Time
	UpdatedAt       time.Time
}

// IsVerified reports whether the email address has been verified.
func (u User) IsVerified() bool { return u.EmailVerifiedAt != nil }

// RefreshToken is one link of a refresh-token family. Rotation replaces it
// with the next link and revokes it; presenting a replaced token again is
// reuse, and revokes the whole family.
type RefreshToken struct {
	ID       int64
	UserID   int64
	FamilyID string
	// TokenHash is HashToken of the opaque token; the token itself is never
	// stored.
	TokenHash  string
	DeviceName *string
	// AuthenticatedAt is when the user last proved their password in this
	// family: login, register or reauth. Rotation keeps it.
	AuthenticatedAt time.Time
	ExpiresAt       time.Time
	RevokedAt       *time.Time
	ReplacedByID    *int64
}

// IsRotated reports whether the token has been replaced by a newer one.
func (t RefreshToken) IsRotated() bool { return t.ReplacedByID != nil }

// IsRevoked reports whether the token was revoked (rotated, logged out, or
// its family revoked for reuse).
func (t RefreshToken) IsRevoked() bool { return t.RevokedAt != nil }

// IsExpired reports whether the token has expired at now.
func (t RefreshToken) IsExpired(now time.Time) bool { return !now.Before(t.ExpiresAt) }

// HashToken is the stored form of an opaque refresh token: hex SHA-256, as
// the PHP API stores it.
func HashToken(plain string) string {
	sum := sha256.Sum256([]byte(plain))

	return hex.EncodeToString(sum[:])
}

// AccessClaims are an access token's claims.
type AccessClaims struct {
	UserID   int64
	FamilyID string
	TokenID  string
	// AuthTime is the auth_time claim: the family's AuthenticatedAt when
	// the token was issued.
	AuthTime  time.Time
	IssuedAt  time.Time
	ExpiresAt time.Time
}

// TokenPair is what register, login and refresh hand out.
type TokenPair struct {
	AccessToken string
	// ExpiresIn is the access token's lifetime in seconds.
	ExpiresIn        int
	RefreshToken     string
	RefreshExpiresAt time.Time
}

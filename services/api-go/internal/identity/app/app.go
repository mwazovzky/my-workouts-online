// Package app holds identity's use cases. Service is the module's only
// entry point: the HTTP adapter, the bearer middleware (through
// Authenticate) and the CLI commands call it.
package app

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"fmt"
	"log/slog"
	"math"
	"strconv"
	"strings"
	"time"

	"github.com/google/uuid"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/txn"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/validate"
)

// The login lockout, as in the PHP LoginRequest: five failures per
// lowercased email and IP, then wait for the window that opened at the
// first failure.
const (
	maxLoginFailures = 5
	lockoutWindow    = time.Minute
)

// minPasswordLength is Laravel's Password::defaults() minimum.
const minPasswordLength = 8

// Deps are the Service's collaborators.
type Deps struct {
	Users         ports.Users
	RefreshTokens ports.RefreshTokens
	Hasher        ports.PasswordHasher
	Signer        ports.TokenSigner
	LoginAttempts ports.LoginAttempts
	Tx            txn.Transactor
	Clock         clock.Clock
	Logger        *slog.Logger
	// AccessTTL and RefreshTTL are the token lifetimes (JWT_ACCESS_TTL,
	// JWT_REFRESH_TTL).
	AccessTTL  time.Duration
	RefreshTTL time.Duration
}

// Service runs identity's use cases.
type Service struct {
	Deps
	// dummyHash is checked when a login names no account, so a missing
	// account takes as long to reject as a wrong password.
	dummyHash string
}

// New returns a Service.
func New(deps Deps) (*Service, error) {
	dummy, err := deps.Hasher.Hash("not a password anyone has")
	if err != nil {
		return nil, fmt.Errorf("identity: %w", err)
	}

	return &Service{Deps: deps, dummyHash: dummy}, nil
}

// Session is a signed-in user with a fresh token pair.
type Session struct {
	User domain.User
	Pair domain.TokenPair
}

// RegisterInput is a sign-up.
type RegisterInput struct {
	Name       string
	Email      string
	Password   string
	DeviceName *string
}

// Register creates an account and signs it in. The email must already be
// lowercase and unused; the password needs at least eight characters.
func (s *Service) Register(ctx context.Context, in RegisterInput) (Session, error) {
	v := validate.New()

	if v.Required("name", in.Name) {
		v.MaxLen("name", in.Name, 255)
	}

	if v.Required("email", in.Email) &&
		v.Check(in.Email == strings.ToLower(in.Email), "email", apperr.FieldInvalidFormat, "validation.lowercase", nil) &&
		v.Email("email", in.Email) {
		v.MaxLen("email", in.Email, 255)
	}

	if v.Required("password", in.Password) {
		v.MinLen("password", in.Password, minPasswordLength)
	}

	if err := v.Err(); err != nil {
		return Session{}, err
	}

	hash, err := s.Hasher.Hash(in.Password)
	if err != nil {
		return Session{}, fmt.Errorf("register: %w", err)
	}

	var session Session

	err = s.Tx.InTx(ctx, func(ctx context.Context) error {
		user, err := s.Users.Create(ctx, ports.NewUser{Name: in.Name, Email: in.Email, PasswordHash: hash})
		if s.Users.IsDuplicateEmail(err) {
			v := validate.New()
			v.Fail("email", apperr.FieldNotUnique, "validation.unique", nil)

			return v.Err()
		}

		if err != nil {
			return err
		}

		pair, _, err := s.issue(ctx, user, uuid.NewString(), in.DeviceName, s.Clock.Now())
		session = Session{User: user, Pair: pair}

		return err
	})
	if err != nil {
		return Session{}, err
	}

	s.Logger.InfoContext(ctx, "user.registered", "user_id", session.User.ID)

	return session, nil
}

// LoginInput is a sign-in.
type LoginInput struct {
	Email      string
	Password   string
	DeviceName *string
	// ClientIP keys the lockout together with the email.
	ClientIP string
}

// Login checks the credentials and opens a new refresh-token family. Wrong
// credentials are a 401 invalid_credentials; the sixth failure within a
// minute for one email and IP is a 429 too_many_attempts until the minute
// is over.
func (s *Service) Login(ctx context.Context, in LoginInput) (Session, error) {
	key := "login:" + strings.ToLower(in.Email) + "|" + in.ClientIP

	failures, resetIn, err := s.LoginAttempts.Hits(ctx, key)
	if err != nil {
		return Session{}, fmt.Errorf("login: %w", err)
	}

	if failures >= maxLoginFailures {
		seconds := int(math.Ceil(resetIn.Seconds()))

		return Session{}, &apperr.Error{
			Kind:       apperr.KindRateLimited,
			Code:       apperr.CodeTooManyAttempts,
			Detail:     "auth.throttle",
			Args:       map[string]string{"seconds": strconv.Itoa(seconds), "minutes": strconv.Itoa((seconds + 59) / 60)},
			RetryAfter: resetIn,
		}
	}

	user, err := s.Users.ByEmail(ctx, in.Email)

	switch {
	case apperr.HasKind(err, apperr.KindNotFound):
		s.Hasher.Matches(s.dummyHash, in.Password)
	case err != nil:
		return Session{}, err
	}

	if err != nil || !s.Hasher.Matches(user.PasswordHash, in.Password) {
		if err := s.LoginAttempts.Hit(ctx, key, lockoutWindow); err != nil {
			return Session{}, fmt.Errorf("login: %w", err)
		}

		return Session{}, domain.ErrInvalidCredentials()
	}

	if err := s.LoginAttempts.Clear(ctx, key); err != nil {
		return Session{}, fmt.Errorf("login: %w", err)
	}

	pair, _, err := s.issue(ctx, user, uuid.NewString(), in.DeviceName, s.Clock.Now())
	if err != nil {
		return Session{}, err
	}

	s.Logger.InfoContext(ctx, "auth.login", "user_id", user.ID)

	return Session{User: user, Pair: pair}, nil
}

// Refresh rotates a refresh token: the presented token is revoked and
// replaced by a new one in the same family, which keeps the family's
// authentication time. Presenting a rotated token again, or rotating one
// token twice concurrently, is reuse: the family is revoked.
func (s *Service) Refresh(ctx context.Context, plain string) (Session, error) {
	if plain == "" {
		return Session{}, domain.ErrRefreshTokenMissing()
	}

	current, err := s.RefreshTokens.ByHash(ctx, domain.HashToken(plain))
	if apperr.HasKind(err, apperr.KindNotFound) {
		return Session{}, domain.ErrRefreshTokenInvalid()
	}

	if err != nil {
		return Session{}, err
	}

	now := s.Clock.Now()

	switch {
	case current.IsRotated():
		return Session{}, s.reused(ctx, current.FamilyID)
	case current.IsRevoked():
		return Session{}, domain.ErrRefreshTokenInvalid()
	case current.IsExpired(now):
		return Session{}, domain.ErrRefreshTokenExpired()
	}

	var (
		session            Session
		reusedConcurrently bool
	)

	err = s.Tx.InTx(ctx, func(ctx context.Context) error {
		locked, err := s.RefreshTokens.Lock(ctx, current.ID)
		if err != nil {
			return err
		}

		// Another refresh rotated it while this one waited for the lock.
		if locked.IsRotated() || locked.IsRevoked() {
			reusedConcurrently = true

			return nil
		}

		user, err := s.Users.ByID(ctx, locked.UserID)
		if err != nil {
			return err
		}

		pair, replacement, err := s.issue(ctx, user, locked.FamilyID, locked.DeviceName, locked.AuthenticatedAt)
		if err != nil {
			return err
		}

		session = Session{User: user, Pair: pair}

		return s.RefreshTokens.MarkReplaced(ctx, locked.ID, replacement.ID, now)
	})
	if err != nil {
		return Session{}, err
	}

	if reusedConcurrently {
		return Session{}, s.reused(ctx, current.FamilyID)
	}

	return session, nil
}

func (s *Service) reused(ctx context.Context, familyID string) error {
	if err := s.RefreshTokens.RevokeFamily(ctx, familyID, s.Clock.Now()); err != nil {
		return err
	}

	s.Logger.WarnContext(ctx, "auth.refresh_token_reused", "family_id", familyID)

	return domain.ErrRefreshTokenReused()
}

// Logout revokes the caller's refresh-token family; other devices stay
// signed in.
func (s *Service) Logout(ctx context.Context, p auth.Principal) error {
	if err := s.RefreshTokens.RevokeFamily(ctx, p.Family, s.Clock.Now()); err != nil {
		return err
	}

	s.Logger.InfoContext(ctx, "auth.logout", "user_id", p.UserID)

	return nil
}

// StepUp is a fresh access token after reauthentication.
type StepUp struct {
	AccessToken string
	ExpiresIn   int
}

// Reauth checks the password again and returns an access token whose
// auth_time is now; the family's later refreshes keep the new time.
func (s *Service) Reauth(ctx context.Context, p auth.Principal, password string) (StepUp, error) {
	user, err := s.Users.ByID(ctx, p.UserID)
	if err != nil {
		return StepUp{}, err
	}

	if !s.Hasher.Matches(user.PasswordHash, password) {
		v := validate.New()
		v.Fail("password", apperr.FieldMismatch, "auth.password", nil)

		return StepUp{}, v.Err()
	}

	now := s.Clock.Now().Truncate(time.Second)

	if err := s.RefreshTokens.StepUp(ctx, p.Family, user.ID, now); err != nil {
		return StepUp{}, err
	}

	access, err := s.Signer.Sign(s.claims(user.ID, p.Family, now, s.Clock.Now()))
	if err != nil {
		return StepUp{}, err
	}

	return StepUp{AccessToken: access, ExpiresIn: int(s.AccessTTL.Seconds())}, nil
}

// Authenticate resolves a bearer access token to the caller. It implements
// the HTTP platform's Authenticator.
func (s *Service) Authenticate(ctx context.Context, bearer string) (auth.Principal, error) {
	claims, err := s.Signer.Verify(bearer)
	if err != nil {
		return auth.Principal{}, err
	}

	user, err := s.Users.ByID(ctx, claims.UserID)
	if apperr.HasKind(err, apperr.KindNotFound) {
		return auth.Principal{}, domain.ErrUnauthenticated()
	}

	if err != nil {
		return auth.Principal{}, err
	}

	return auth.Principal{
		UserID:        user.ID,
		IsAdmin:       user.IsAdmin,
		EmailVerified: user.IsVerified(),
		AuthTime:      claims.AuthTime,
		Family:        claims.FamilyID,
	}, nil
}

// issue stores a new refresh token in familyID and signs an access token for
// it. authTime is the family's authentication time.
func (s *Service) issue(ctx context.Context, user domain.User, familyID string, device *string, authTime time.Time) (domain.TokenPair, domain.RefreshToken, error) {
	now := s.Clock.Now()

	secret := make([]byte, 32)
	_, _ = rand.Read(secret) // crypto/rand.Read never fails

	plain := hex.EncodeToString(secret)

	stored, err := s.RefreshTokens.Create(ctx, domain.RefreshToken{
		UserID:          user.ID,
		FamilyID:        familyID,
		TokenHash:       domain.HashToken(plain),
		DeviceName:      device,
		AuthenticatedAt: authTime.Truncate(time.Second),
		ExpiresAt:       now.Add(s.RefreshTTL),
	}, now)
	if err != nil {
		return domain.TokenPair{}, domain.RefreshToken{}, err
	}

	access, err := s.Signer.Sign(s.claims(user.ID, familyID, stored.AuthenticatedAt, now))
	if err != nil {
		return domain.TokenPair{}, domain.RefreshToken{}, err
	}

	return domain.TokenPair{
		AccessToken:      access,
		ExpiresIn:        int(s.AccessTTL.Seconds()),
		RefreshToken:     plain,
		RefreshExpiresAt: stored.ExpiresAt,
	}, stored, nil
}

func (s *Service) claims(userID int64, familyID string, authTime, now time.Time) domain.AccessClaims {
	return domain.AccessClaims{
		UserID:    userID,
		FamilyID:  familyID,
		TokenID:   uuid.NewString(),
		AuthTime:  authTime,
		IssuedAt:  now,
		ExpiresAt: now.Add(s.AccessTTL),
	}
}

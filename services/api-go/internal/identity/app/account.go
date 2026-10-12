package app

import (
	"context"
	"crypto/rand"
	"crypto/subtle"
	"encoding/hex"
	"fmt"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/auth"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/validate"
)

// Link lifetimes, as Laravel's auth.verification.expire and
// auth.passwords.users.expire.
const (
	verificationTTL = 60 * time.Minute
	resetTTL        = 60 * time.Minute
)

// VerifyInput is a verification link posted back by the SPA.
type VerifyInput struct {
	ID        int64
	Hash      string
	Expires   int64
	Signature string
}

// VerifyEmail marks the caller's email verified from the parameters of the
// emailed link. A link for another account is 403
// invalid_verification_link; a tampered or expired one 403
// invalid_signature. Verifying twice succeeds.
func (s *Service) VerifyEmail(ctx context.Context, p auth.Principal, in VerifyInput) error {
	user, err := s.Users.ByID(ctx, p.UserID)
	if err != nil {
		return err
	}

	ownHash := s.Links.EmailHash(user.Email)
	if in.ID != user.ID || subtle.ConstantTimeCompare([]byte(in.Hash), []byte(ownHash)) != 1 {
		return domain.ErrVerificationLinkForAnotherAccount()
	}

	now := s.Clock.Now()
	if !s.Links.VerificationValid(in.ID, in.Hash, in.Expires, in.Signature) || now.Unix() > in.Expires {
		return domain.ErrVerificationLinkInvalid()
	}

	if user.IsVerified() {
		return nil
	}

	if err := s.Users.MarkVerified(ctx, user.ID, now); err != nil {
		return err
	}

	s.Logger.InfoContext(ctx, "user.email_verified", "user_id", user.ID)

	return nil
}

// ResendVerification emails a new verification link unless the caller is
// verified already; either way the request is accepted.
func (s *Service) ResendVerification(ctx context.Context, p auth.Principal, locale string) error {
	user, err := s.Users.ByID(ctx, p.UserID)
	if err != nil {
		return err
	}

	if !user.IsVerified() {
		s.sendVerification(ctx, user, locale)
	}

	return nil
}

// sendVerification emails the verification link. A mail failure is logged,
// not returned: the account exists either way and the user can ask again.
func (s *Service) sendVerification(ctx context.Context, user domain.User, locale string) {
	link := s.Links.VerificationURL(user.ID, user.Email, s.Clock.Now().Add(verificationTTL))

	if err := s.Notifier.VerifyEmail(ctx, user.Email, link, locale); err != nil {
		s.Logger.ErrorContext(ctx, "verification email not sent", "user_id", user.ID, "error", err.Error())
	}
}

// ForgotPassword emails a reset link when an account has the email. It
// never reports whether one does, and swallows mail failures, so the answer
// is the same 202 for every address.
func (s *Service) ForgotPassword(ctx context.Context, email, locale string) error {
	user, err := s.Users.ByEmail(ctx, email)
	if apperr.HasKind(err, apperr.KindNotFound) {
		return nil
	}

	if err != nil {
		return err
	}

	secret := make([]byte, 32)
	_, _ = rand.Read(secret) // crypto/rand.Read never fails

	token := hex.EncodeToString(secret)

	if err := s.Resets.Put(ctx, domain.PasswordReset{
		Email: user.Email, TokenHash: domain.HashToken(token), CreatedAt: s.Clock.Now(),
	}); err != nil {
		return err
	}

	link := s.Links.ResetURL(token, user.Email)
	if err := s.Notifier.ResetPassword(ctx, user.Email, link, locale, int(resetTTL.Minutes())); err != nil {
		s.Logger.ErrorContext(ctx, "reset email not sent", "user_id", user.ID, "error", err.Error())
	}

	return nil
}

// ResetInput is a password reset from an emailed link.
type ResetInput struct {
	Token    string
	Email    string
	Password string
}

// ResetPassword sets a new password from a pending reset, uses the reset
// up, and revokes every refresh-token family of the account. An unknown,
// used or expired token is 422 on /token.
func (s *Service) ResetPassword(ctx context.Context, in ResetInput) error {
	v := validate.New()
	checkNewPassword(v, "password", in.Password)

	if err := v.Err(); err != nil {
		return err
	}

	invalid := func() error {
		v := validate.New()
		v.Fail("token", apperr.FieldInvalidValue, "passwords.token", nil)

		return v.Err()
	}

	reset, err := s.Resets.ByEmail(ctx, in.Email)
	if apperr.HasKind(err, apperr.KindNotFound) {
		return invalid()
	}

	if err != nil {
		return err
	}

	now := s.Clock.Now()
	if reset.IsExpired(now, resetTTL) || subtle.ConstantTimeCompare([]byte(reset.TokenHash), []byte(domain.HashToken(in.Token))) != 1 {
		return invalid()
	}

	user, err := s.Users.ByEmail(ctx, in.Email)
	if apperr.HasKind(err, apperr.KindNotFound) {
		return invalid()
	}

	if err != nil {
		return err
	}

	hash, err := s.Hasher.Hash(in.Password)
	if err != nil {
		return fmt.Errorf("reset password: %w", err)
	}

	err = s.Tx.InTx(ctx, func(ctx context.Context) error {
		if err := s.Users.SetPassword(ctx, user.ID, hash); err != nil {
			return err
		}

		if err := s.Resets.Delete(ctx, in.Email); err != nil {
			return err
		}

		return s.RefreshTokens.RevokeUserFamilies(ctx, user.ID, "", now)
	})
	if err != nil {
		return err
	}

	s.Logger.InfoContext(ctx, "auth.password_reset", "user_id", user.ID)

	return nil
}

// Me returns the caller's account.
func (s *Service) Me(ctx context.Context, p auth.Principal) (domain.User, error) {
	return s.Users.ByID(ctx, p.UserID)
}

// MePatch is a merge patch of the caller's account: nil leaves a field as it is.
type MePatch struct {
	Name            *string
	Email           *string
	Locale          *string
	ThemePreference *string
}

// UpdateMe applies the fields present. A changed email clears the
// verification and emails a new link (in locale). The contract rejects an
// empty patch before it gets here (minProperties: 1).
func (s *Service) UpdateMe(ctx context.Context, p auth.Principal, patch MePatch, locale string) (domain.User, error) {
	v := validate.New()

	if patch.Name != nil && v.Required("name", *patch.Name) {
		v.MaxLen("name", *patch.Name, 255)
	}

	if patch.Email != nil && v.Required("email", *patch.Email) {
		checkEmail(v, *patch.Email)
	}

	if err := v.Err(); err != nil {
		return domain.User{}, err
	}

	user, err := s.Users.ByID(ctx, p.UserID)
	if err != nil {
		return domain.User{}, err
	}

	emailChanged := patch.Email != nil && *patch.Email != user.Email

	apply(&user.Name, patch.Name)
	apply(&user.Email, patch.Email)
	apply(&user.Locale, patch.Locale)
	apply(&user.ThemePreference, patch.ThemePreference)

	if emailChanged {
		user.EmailVerifiedAt = nil
	}

	updated, err := s.Users.Update(ctx, user)
	if s.Users.IsDuplicateEmail(err) {
		v := validate.New()
		v.Fail("email", apperr.FieldNotUnique, "validation.unique", nil)

		return domain.User{}, v.Err()
	}

	if err != nil {
		return domain.User{}, err
	}

	if emailChanged {
		s.sendVerification(ctx, updated, locale)
	}

	return updated, nil
}

func apply(field *string, value *string) {
	if value != nil {
		*field = *value
	}
}

// UpdatePassword changes the caller's password after checking the current
// one, and signs out every other device: only the caller's family survives.
func (s *Service) UpdatePassword(ctx context.Context, p auth.Principal, current, password string) error {
	user, err := s.Users.ByID(ctx, p.UserID)
	if err != nil {
		return err
	}

	v := validate.New()
	v.Check(s.Hasher.Matches(user.PasswordHash, current), "current_password", apperr.FieldMismatch, "validation.current_password", nil)
	checkNewPassword(v, "password", password)

	if err := v.Err(); err != nil {
		return err
	}

	hash, err := s.Hasher.Hash(password)
	if err != nil {
		return fmt.Errorf("update password: %w", err)
	}

	return s.Tx.InTx(ctx, func(ctx context.Context) error {
		if err := s.Users.SetPassword(ctx, user.ID, hash); err != nil {
			return err
		}

		return s.RefreshTokens.RevokeUserFamilies(ctx, user.ID, p.Family, s.Clock.Now())
	})
}

// DeleteMe deletes the caller's account after checking the password. Its
// refresh tokens, workouts and enrollments go with it.
func (s *Service) DeleteMe(ctx context.Context, p auth.Principal, password string) error {
	user, err := s.Users.ByID(ctx, p.UserID)
	if err != nil {
		return err
	}

	if !s.Hasher.Matches(user.PasswordHash, password) {
		v := validate.New()
		v.Fail("password", apperr.FieldMismatch, "validation.current_password", nil)

		return v.Err()
	}

	if err := s.Users.Delete(ctx, user.ID); err != nil {
		return err
	}

	s.Logger.InfoContext(ctx, "user.deleted", "user_id", user.ID)

	return nil
}

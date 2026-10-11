package app

import (
	"context"
	"errors"
	"fmt"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/domain"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
)

// The demo admin `workouts seed` creates, as the PHP UserSeeder does. The
// contract tests sign in with it.
const (
	DemoAdminEmail    = "test@example.com"
	DemoAdminPassword = "password"
	demoAdminName     = "John Doe"
)

// SeedDemoAdmin creates the verified demo admin, or resets it to the known
// password. The caller refuses to run it in production.
func (s *Service) SeedDemoAdmin(ctx context.Context) (domain.User, error) {
	hash, err := s.Hasher.Hash(DemoAdminPassword)
	if err != nil {
		return domain.User{}, fmt.Errorf("seed: %w", err)
	}

	var user domain.User

	err = s.Tx.InTx(ctx, func(ctx context.Context) error {
		user, err = s.Users.UpsertVerified(ctx, ports.NewUser{Name: demoAdminName, Email: DemoAdminEmail, PasswordHash: hash}, s.Clock.Now())
		if err != nil {
			return err
		}

		user.IsAdmin = true

		return s.Users.GrantRole(ctx, user.ID, domain.RoleAdmin)
	})

	return user, err
}

// GrantAdmin outcomes.
var (
	// ErrNoSuchUser: no account has the email.
	ErrNoSuchUser = errors.New("no user is registered with this email")
	// ErrUnverified: the account has not verified its email; nothing changed.
	ErrUnverified = errors.New("the email address is not verified; nothing was changed")
)

// GrantAdmin gives a verified account the Admin role, as the PHP
// user:grant-admin command does. It reports whether the account already was
// an admin.
func (s *Service) GrantAdmin(ctx context.Context, email string) (alreadyAdmin bool, err error) {
	user, err := s.Users.ByEmail(ctx, email)
	if apperr.HasKind(err, apperr.KindNotFound) {
		return false, ErrNoSuchUser
	}

	if err != nil {
		return false, err
	}

	if !user.IsVerified() {
		return false, ErrUnverified
	}

	if user.IsAdmin {
		return true, nil
	}

	return false, s.Users.GrantRole(ctx, user.ID, domain.RoleAdmin)
}

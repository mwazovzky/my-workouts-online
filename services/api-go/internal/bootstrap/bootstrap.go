// Package bootstrap is the composition root: the only package that imports
// every module and wires adapters into use cases. cmd/workouts and the HTTP
// tests build the application through New.
package bootstrap

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"slices"

	"github.com/jackc/pgx/v5/pgxpool"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/bcrypt"
	identityhttp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/http"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/jwt"
	identitypg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/postgres"
	identityapp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/health"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/meta"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/ratelimit"
)

// Deps are the process-level dependencies the application is built from.
type Deps struct {
	Config config.Config
	Logger *slog.Logger
	Clock  clock.Clock
	// Pool is the database. New takes ownership: App.Close closes it.
	Pool *pgxpool.Pool
}

// App is the wired application.
type App struct {
	// Handler serves every route.
	Handler http.Handler
	// Readiness backs /health/ready; components register their checks on it
	// and the server flips it to draining on shutdown.
	Readiness *health.Readiness
	// Identity runs identity's use cases for the CLI (seed, user grant-admin).
	Identity *identityapp.Service

	closers []func(context.Context) error
}

// New wires the application. It fails only when an embedded asset (the
// contract, a message catalog) does not load.
func New(deps Deps) (*App, error) {
	cfg := deps.Config

	bundle, err := i18n.NewBundle()
	if err != nil {
		return nil, err
	}

	keys, err := jwt.ParseKeys(cfg.JWT.PrivateKey, cfg.JWT.PublicKey, cfg.JWT.KeyID)
	if err != nil {
		return nil, fmt.Errorf("bootstrap: %w", err)
	}

	// One in-memory limiter, its keys namespaced per use: the global API
	// limit, per-operation throttles and the login lockout.
	limiter := ratelimit.NewMemory(deps.Clock)
	tx := postgres.NewTransactor(deps.Pool)
	signer := jwt.NewSigner(jwt.Config{
		Keys: keys, Issuer: cfg.JWT.Issuer, Audience: cfg.JWT.Audience, Leeway: cfg.JWT.Leeway(), Clock: deps.Clock,
	})

	identity, err := identityapp.New(identityapp.Deps{
		Users:         identitypg.NewUsers(tx),
		RefreshTokens: identitypg.NewRefreshTokens(tx),
		Hasher:        bcrypt.New(cfg.BcryptRounds),
		Signer:        signer,
		LoginAttempts: limiter,
		Tx:            tx,
		Clock:         deps.Clock,
		Logger:        deps.Logger,
		AccessTTL:     cfg.JWT.AccessTTL(),
		RefreshTTL:    cfg.JWT.RefreshTTL(),
	})
	if err != nil {
		return nil, err
	}

	server, err := httpapi.NewServer(httpapi.Options{
		AppURL:             cfg.AppURL,
		RateLimitPerMinute: cfg.RateLimitPerMinute,
		Bundle:             bundle,
		Logger:             deps.Logger,
		Clock:              deps.Clock,
		Limiter:            limiter,
		Authenticator:      identity,
		Throttles:          identityhttp.Throttles(),
	})
	if err != nil {
		return nil, err
	}

	readiness := health.NewReadiness(deps.Clock, deps.Logger)
	readiness.AddCheck("database", postgres.ReadinessCheck(deps.Pool))
	readiness.AddCheck("jwt", func(context.Context) error { return signer.Ready() })

	server.Mount(api{
		Liveness:  health.NewLiveness(deps.Clock),
		Readiness: readiness,
		Handler:   meta.NewHandler(bundle),
		Handlers:  identityhttp.NewHandlers(identity, signer, identityhttp.Config{CookieSecure: cfg.JWT.CookieSecure, Clock: deps.Clock}),
	})
	server.Handle("GET /up", http.HandlerFunc(health.Up))

	return &App{
		Handler:   server.Handler(),
		Readiness: readiness,
		Identity:  identity,
		closers: []func(context.Context) error{
			func(context.Context) error { deps.Pool.Close(); return nil },
		},
	}, nil
}

// Close releases resources in the reverse order of acquisition. It runs after
// the HTTP server has shut down, so no request can still be using them.
func (a *App) Close(ctx context.Context) error {
	var errs []error
	for _, closeFn := range slices.Backward(a.closers) {
		errs = append(errs, closeFn(ctx))
	}

	return errors.Join(errs...)
}

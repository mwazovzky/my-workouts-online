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
	"os"
	"slices"

	"github.com/jackc/pgx/v5/pgxpool"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/catalogfile"
	cataloghttp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/http"
	catalogpg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/postgres"
	catalogapp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app/catalogsync"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/bcrypt"
	identityhttp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/http"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/jwt"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/links"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/notify"
	identitypg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/postgres"
	identityapp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	kernelmail "github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/health"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	platformmail "github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/mail"
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
	// Mailer sends email; nil logs every message instead (the dev stack and
	// CI). Tests pass a mail.Fake to read what was sent.
	Mailer kernelmail.Mailer
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
	// CatalogSync applies the YAML catalog in CATALOG_DIR (catalog sync, seed).
	CatalogSync *catalogsync.Syncer

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

	mailer := deps.Mailer
	if mailer == nil {
		mailer = platformmail.NewLog(deps.Logger)
	}

	notifier, err := notify.New(mailer, cfg.AppName)
	if err != nil {
		return nil, err
	}

	identity, err := identityapp.New(identityapp.Deps{
		Users:         identitypg.NewUsers(tx),
		RefreshTokens: identitypg.NewRefreshTokens(tx),
		Hasher:        bcrypt.New(cfg.BcryptRounds),
		Signer:        signer,
		LoginAttempts: limiter,
		Resets:        identitypg.NewPasswordResets(tx),
		Links:         links.New(cfg.FrontendURL, cfg.LinkSigningKey),
		Notifier:      notifier,
		Tx:            tx,
		Clock:         deps.Clock,
		Logger:        deps.Logger,
		AccessTTL:     cfg.JWT.AccessTTL(),
		RefreshTTL:    cfg.JWT.RefreshTTL(),
	})
	if err != nil {
		return nil, err
	}

	catalog := catalogapp.New(catalogapp.Deps{
		Programs:  catalogpg.NewPrograms(tx),
		Templates: catalogpg.NewTemplates(tx),
		Exercises: catalogpg.NewExercises(tx),
		Clock:     deps.Clock,
		Logger:    deps.Logger,
	})

	locales := make([]string, 0, len(bundle.Locales()))
	for _, locale := range bundle.Locales() {
		locales = append(locales, string(locale))
	}

	catalogSync := catalogsync.New(catalogsync.Deps{
		Source:  catalogfile.New(os.DirFS(cfg.CatalogDir)),
		Store:   catalogpg.NewStore(tx),
		Tx:      tx,
		Clock:   deps.Clock,
		Locales: locales,
	})

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
		Identity:  identityhttp.New(identity, signer, identityhttp.Config{CookieSecure: cfg.JWT.CookieSecure, Clock: deps.Clock}),
		Catalog:   cataloghttp.New(catalog),
	})
	server.Handle("GET /up", http.HandlerFunc(health.Up))

	return &App{
		Handler:     server.Handler(),
		Readiness:   readiness,
		Identity:    identity,
		CatalogSync: catalogSync,
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

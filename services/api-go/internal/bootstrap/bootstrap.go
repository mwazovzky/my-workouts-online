// Package bootstrap is the composition root: the only package that imports
// every module and wires adapters into use cases. cmd/workouts and the HTTP
// tests build the application through New.
package bootstrap

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"slices"

	"github.com/jackc/pgx/v5/pgxpool"

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

	closers []func(context.Context) error
}

// New wires the application. It fails only when an embedded asset (the
// contract, a message catalog) does not load.
func New(deps Deps) (*App, error) {
	bundle, err := i18n.NewBundle()
	if err != nil {
		return nil, err
	}

	server, err := httpapi.NewServer(httpapi.Options{
		AppURL:             deps.Config.AppURL,
		RateLimitPerMinute: deps.Config.RateLimitPerMinute,
		Bundle:             bundle,
		Logger:             deps.Logger,
		Clock:              deps.Clock,
		Limiter:            ratelimit.NewMemory(deps.Clock),
	})
	if err != nil {
		return nil, err
	}

	readiness := health.NewReadiness(deps.Clock, deps.Logger)
	readiness.AddCheck("database", postgres.ReadinessCheck(deps.Pool))

	server.Mount(api{
		Liveness:  health.NewLiveness(deps.Clock),
		Readiness: readiness,
		Handler:   meta.NewHandler(bundle),
	})
	server.Handle("GET /up", http.HandlerFunc(health.Up))

	return &App{
		Handler:   server.Handler(),
		Readiness: readiness,
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

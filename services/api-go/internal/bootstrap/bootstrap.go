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

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/health"
)

// Deps are the process-level dependencies the application is built from.
type Deps struct {
	Logger *slog.Logger
	Clock  clock.Clock
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

// New wires the application.
func New(deps Deps) *App {
	readiness := health.NewReadiness(deps.Clock, deps.Logger)

	mux := http.NewServeMux()
	mux.Handle("GET /health", health.NewLiveness(deps.Clock, deps.Logger))
	mux.Handle("GET /health/ready", readiness)
	mux.HandleFunc("GET /up", health.Up)

	return &App{Handler: mux, Readiness: readiness}
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

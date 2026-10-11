// Package health serves the liveness and readiness probes (/health,
// /health/ready, /up) with the response shapes of the PHP API, using the
// types generated from the contract.
package health

import (
	"context"
	"io"
	"log/slog"
	"maps"
	"net/http"
	"sync"
	"sync/atomic"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
)

// checkTimeout bounds one component check, so a hung dependency reports down
// instead of hanging the probe.
const checkTimeout = 2 * time.Second

// Check reports whether one dependency is usable; a non-nil error means down.
type Check func(ctx context.Context) error

// NewLiveness returns the /health handler: 200 whenever the process answers.
func NewLiveness(clk clock.Clock, logger *slog.Logger) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		response := gen.GetHealth200JSONResponse{
			Status:    gen.GetHealth200JSONResponseBodyStatusOk,
			Timestamp: timestamp(clk),
		}
		if err := response.VisitGetHealthResponse(w); err != nil {
			logger.WarnContext(r.Context(), "health response not written", "error", err)
		}
	})
}

// Up is the /up handler kept for parity with the PHP framework probe.
func Up(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "text/plain; charset=utf-8")
	_, _ = io.WriteString(w, "ok\n")
}

// Readiness is the /health/ready handler. Components register a Check as they
// are wired; the probe answers 503 when any check fails or once draining
// starts during shutdown.
type Readiness struct {
	clock    clock.Clock
	logger   *slog.Logger
	mu       sync.RWMutex
	checks   map[string]Check
	draining atomic.Bool
}

// NewReadiness returns a readiness probe with no components.
func NewReadiness(clk clock.Clock, logger *slog.Logger) *Readiness {
	return &Readiness{clock: clk, logger: logger, checks: map[string]Check{}}
}

// Register adds a component; name is the key in the response's components map.
func (r *Readiness) Register(name string, check Check) {
	r.mu.Lock()
	defer r.mu.Unlock()

	r.checks[name] = check
}

// StartDraining makes every later probe answer 503, telling a proxy to stop
// routing new requests here while in-flight ones finish.
func (r *Readiness) StartDraining() {
	r.draining.Store(true)
}

// ServeHTTP runs every check and writes the readiness document.
func (r *Readiness) ServeHTTP(w http.ResponseWriter, req *http.Request) {
	r.mu.RLock()
	checks := maps.Clone(r.checks)
	r.mu.RUnlock()

	components := make(map[string]gen.ReadinessComponents, len(checks))
	degraded := r.draining.Load()

	for name, check := range checks {
		components[name] = gen.Up

		if err := runCheck(req.Context(), check); err != nil {
			components[name] = gen.Down
			degraded = true

			r.logger.WarnContext(req.Context(), "Readiness check failed", "component", name, "error", err)
		}
	}

	body := gen.Readiness{
		Status:     gen.ReadinessStatusOk,
		Components: components,
		Timestamp:  timestamp(r.clock),
	}

	var err error
	if degraded {
		body.Status = gen.ReadinessStatusDegraded
		err = gen.GetReadiness503JSONResponse(body).VisitGetReadinessResponse(w)
	} else {
		err = gen.GetReadiness200JSONResponse(body).VisitGetReadinessResponse(w)
	}

	if err != nil {
		r.logger.WarnContext(req.Context(), "readiness response not written", "error", err)
	}
}

func runCheck(ctx context.Context, check Check) error {
	ctx, cancel := context.WithTimeout(ctx, checkTimeout)
	defer cancel()

	return check(ctx)
}

// timestamp is whole seconds in UTC, which encodes as 2026-10-07T09:30:00Z.
func timestamp(clk clock.Clock) time.Time {
	return clk.Now().UTC().Truncate(time.Second)
}

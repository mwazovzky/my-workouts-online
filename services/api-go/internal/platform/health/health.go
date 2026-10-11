// Package health implements the liveness and readiness probes. /health and
// /health/ready are contract operations served through the generated strict
// server (GetHealth, GetReadiness); /up is a plain handler kept for parity
// with the PHP framework probe.
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

// Liveness serves /health: 200 whenever the process answers.
type Liveness struct {
	clock clock.Clock
}

// NewLiveness returns the liveness probe.
func NewLiveness(clk clock.Clock) Liveness {
	return Liveness{clock: clk}
}

// GetHealth implements the getHealth operation.
func (l Liveness) GetHealth(context.Context, gen.GetHealthRequestObject) (gen.GetHealthResponseObject, error) {
	return gen.GetHealth200JSONResponse{
		Status:    gen.GetHealth200JSONResponseBodyStatusOk,
		Timestamp: timestamp(l.clock),
	}, nil
}

// Up is the /up handler kept for parity with the PHP framework probe.
func Up(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "text/plain; charset=utf-8")
	_, _ = io.WriteString(w, "ok\n")
}

// Readiness serves /health/ready. Components register a Check as they
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

// AddCheck adds a component; name is the key in the response's components map.
func (r *Readiness) AddCheck(name string, check Check) {
	r.mu.Lock()
	defer r.mu.Unlock()

	r.checks[name] = check
}

// StartDraining makes every later probe answer 503, telling a proxy to stop
// routing new requests here while in-flight ones finish.
func (r *Readiness) StartDraining() {
	r.draining.Store(true)
}

// GetReadiness implements the getReadiness operation: every check runs, and
// any failure, or draining, makes the answer a 503 with status degraded.
func (r *Readiness) GetReadiness(ctx context.Context, _ gen.GetReadinessRequestObject) (gen.GetReadinessResponseObject, error) {
	r.mu.RLock()
	checks := maps.Clone(r.checks)
	r.mu.RUnlock()

	components := make(map[string]gen.ReadinessComponents, len(checks))
	degraded := r.draining.Load()

	for name, check := range checks {
		components[name] = gen.Up

		if err := runCheck(ctx, check); err != nil {
			components[name] = gen.Down
			degraded = true

			r.logger.WarnContext(ctx, "Readiness check failed", "component", name, "error", err)
		}
	}

	body := gen.Readiness{
		Status:     gen.ReadinessStatusOk,
		Components: components,
		Timestamp:  timestamp(r.clock),
	}

	if degraded {
		body.Status = gen.ReadinessStatusDegraded

		return gen.GetReadiness503JSONResponse(body), nil
	}

	return gen.GetReadiness200JSONResponse(body), nil
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

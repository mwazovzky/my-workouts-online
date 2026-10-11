// Package ratelimit is the rate-limiter port and its in-memory adapter. The
// HTTP middleware applies the global API limit through it, and story 4's
// per-route throttles and login lockout use the same port. A shared store
// (Postgres or Redis) can replace Memory when the API runs more than one
// instance.
package ratelimit

import (
	"context"
	"sync"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
)

// Decision is the outcome of one Allow call.
type Decision struct {
	Allowed bool
	// Limit and Remaining describe the current window, for X-RateLimit-* headers.
	Limit     int
	Remaining int
	// RetryAfter is how long until the window resets.
	RetryAfter time.Duration
}

// Limiter counts hits per key in fixed windows.
type Limiter interface {
	// Allow records a hit on key and reports whether it is within limit hits
	// per window.
	Allow(ctx context.Context, key string, limit int, window time.Duration) (Decision, error)
}

// Memory is a fixed-window Limiter in process memory: counts are lost on
// restart and not shared between instances, which suits one API process.
// It is safe for concurrent use.
type Memory struct {
	clock clock.Clock

	mu        sync.Mutex
	windows   map[string]window
	nextSweep time.Time
}

type window struct {
	resetAt time.Time
	hits    int
}

// sweepEvery bounds how often expired windows are dropped, so memory stays
// proportional to the clients of the last few minutes.
const sweepEvery = time.Minute

var _ Limiter = (*Memory)(nil)

// NewMemory returns an empty Memory limiter reading time from clk.
func NewMemory(clk clock.Clock) *Memory {
	return &Memory{clock: clk, windows: map[string]window{}}
}

// Allow records a hit on key. A window opens at the first hit and resets
// window later; hits over limit are denied until then.
func (m *Memory) Allow(_ context.Context, key string, limit int, length time.Duration) (Decision, error) {
	now := m.clock.Now()

	m.mu.Lock()
	defer m.mu.Unlock()

	m.sweep(now)

	w, ok := m.windows[key]
	if !ok || !now.Before(w.resetAt) {
		w = window{resetAt: now.Add(length)}
	}

	w.hits++
	m.windows[key] = w

	return Decision{
		Allowed:    w.hits <= limit,
		Limit:      limit,
		Remaining:  max(0, limit-w.hits),
		RetryAfter: w.resetAt.Sub(now),
	}, nil
}

func (m *Memory) sweep(now time.Time) {
	if now.Before(m.nextSweep) {
		return
	}

	for key, w := range m.windows {
		if !now.Before(w.resetAt) {
			delete(m.windows, key)
		}
	}

	m.nextSweep = now.Add(sweepEvery)
}

// Len returns the number of open windows, for tests.
func (m *Memory) Len() int {
	m.mu.Lock()
	defer m.mu.Unlock()

	return len(m.windows)
}

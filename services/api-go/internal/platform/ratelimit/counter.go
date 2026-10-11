package ratelimit

import (
	"context"
	"time"
)

// Counter counts failures per key in a fixed window that opens at the first
// hit, without denying anything itself: the caller decides what the count
// means. The login lockout (five failures per email and IP, then wait for
// the window) is built on it, as Laravel's RateLimiter hit/tooManyAttempts/
// clear are.
type Counter interface {
	// Hits returns the hits on key in the current window and the time until
	// the window resets; zero hits when no window is open.
	Hits(ctx context.Context, key string) (hits int, resetIn time.Duration, err error)
	// Hit records a hit on key, opening a window of length when none is open.
	Hit(ctx context.Context, key string, length time.Duration) error
	// Clear forgets key.
	Clear(ctx context.Context, key string) error
}

var _ Counter = (*Memory)(nil)

// Hits implements Counter.
func (m *Memory) Hits(_ context.Context, key string) (int, time.Duration, error) {
	now := m.clock.Now()

	m.mu.Lock()
	defer m.mu.Unlock()

	w, ok := m.windows[key]
	if !ok || !now.Before(w.resetAt) {
		return 0, 0, nil
	}

	return w.hits, w.resetAt.Sub(now), nil
}

// Hit implements Counter.
func (m *Memory) Hit(ctx context.Context, key string, length time.Duration) error {
	_, err := m.Allow(ctx, key, 0, length)

	return err
}

// Clear implements Counter.
func (m *Memory) Clear(_ context.Context, key string) error {
	m.mu.Lock()
	defer m.mu.Unlock()

	delete(m.windows, key)

	return nil
}

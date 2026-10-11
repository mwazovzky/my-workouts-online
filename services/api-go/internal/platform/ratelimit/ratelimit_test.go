package ratelimit_test

import (
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/ratelimit"
)

func TestMemoryFixedWindow(t *testing.T) {
	t.Parallel()

	clk := clock.NewFake(time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC))
	limiter := ratelimit.NewMemory(clk)

	allow := func(key string) ratelimit.Decision {
		t.Helper()

		d, err := limiter.Allow(t.Context(), key, 3, time.Minute)
		if err != nil {
			t.Fatal(err)
		}

		return d
	}

	for i, wantRemaining := range []int{2, 1, 0} {
		if d := allow("a"); !d.Allowed || d.Remaining != wantRemaining || d.Limit != 3 {
			t.Errorf("hit %d = %+v, want allowed with %d remaining", i+1, d, wantRemaining)
		}
	}

	clk.Advance(20 * time.Second)

	d := allow("a")
	if d.Allowed || d.Remaining != 0 {
		t.Errorf("hit 4 = %+v, want denied", d)
	}

	if d.RetryAfter != 40*time.Second {
		t.Errorf("RetryAfter = %v, want 40s until the window resets", d.RetryAfter)
	}

	if d := allow("b"); !d.Allowed {
		t.Errorf("another key = %+v, want its own window", d)
	}

	clk.Advance(40 * time.Second)

	if d := allow("a"); !d.Allowed || d.Remaining != 2 || d.RetryAfter != time.Minute {
		t.Errorf("after the reset = %+v, want a fresh window", d)
	}
}

func TestMemoryDropsExpiredWindows(t *testing.T) {
	t.Parallel()

	clk := clock.NewFake(time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC))
	limiter := ratelimit.NewMemory(clk)

	for _, key := range []string{"a", "b", "c"} {
		if _, err := limiter.Allow(t.Context(), key, 1, time.Second); err != nil {
			t.Fatal(err)
		}
	}

	clk.Advance(2 * time.Minute)

	if _, err := limiter.Allow(t.Context(), "d", 1, time.Second); err != nil {
		t.Fatal(err)
	}

	if got := limiter.Len(); got != 1 {
		t.Errorf("Len() = %d, want only the new window", got)
	}
}

func TestMemoryCounter(t *testing.T) {
	t.Parallel()

	clk := clock.NewFake(time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC))
	counter := ratelimit.NewMemory(clk)
	ctx := t.Context()

	hits := func() (int, time.Duration) {
		t.Helper()

		n, resetIn, err := counter.Hits(ctx, "ada|127.0.0.1")
		if err != nil {
			t.Fatal(err)
		}

		return n, resetIn
	}

	if n, _ := hits(); n != 0 {
		t.Fatalf("Hits() before any hit = %d", n)
	}

	for range 3 {
		if err := counter.Hit(ctx, "ada|127.0.0.1", time.Minute); err != nil {
			t.Fatal(err)
		}

		clk.Advance(10 * time.Second)
	}

	if n, resetIn := hits(); n != 3 || resetIn != 30*time.Second {
		t.Errorf("Hits() = %d, %v; want 3, 30s (the window opened at the first hit)", n, resetIn)
	}

	if err := counter.Clear(ctx, "ada|127.0.0.1"); err != nil {
		t.Fatal(err)
	}

	if n, _ := hits(); n != 0 {
		t.Errorf("Hits() after Clear = %d", n)
	}

	if err := counter.Hit(ctx, "ada|127.0.0.1", time.Minute); err != nil {
		t.Fatal(err)
	}

	clk.Advance(time.Minute)

	if n, _ := hits(); n != 0 {
		t.Errorf("Hits() after the window = %d", n)
	}
}

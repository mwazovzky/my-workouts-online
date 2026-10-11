package health_test

import (
	"context"
	"errors"
	"flag"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/health"
)

var update = flag.Bool("update", false, "rewrite the golden files in testdata")

type fixedClock struct{ now time.Time }

func (c fixedClock) Now() time.Time { return c.now }

var clk = fixedClock{now: time.Date(2026, 10, 7, 9, 30, 0, 123_000_000, time.UTC)}

func discardLogger() *slog.Logger {
	return slog.New(slog.DiscardHandler)
}

func up(context.Context) error { return nil }

func down(context.Context) error { return errors.New("connection refused") }

func TestLiveness(t *testing.T) {
	t.Parallel()

	rec := serve(t, health.NewLiveness(clk, discardLogger()), "/health")

	assertResponse(t, rec, http.StatusOK, "application/json", "liveness.golden.json")
}

func TestUp(t *testing.T) {
	t.Parallel()

	rec := serve(t, http.HandlerFunc(health.Up), "/up")

	if rec.Code != http.StatusOK {
		t.Errorf("status = %d, want %d", rec.Code, http.StatusOK)
	}
}

func TestReadiness(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name       string
		checks     map[string]health.Check
		draining   bool
		wantStatus int
		golden     string
	}{
		{"no components", nil, false, http.StatusOK, "ready-empty.golden.json"},
		{"all components up", map[string]health.Check{"database": up, "jwt": up}, false, http.StatusOK, "ready-up.golden.json"},
		{"a component down", map[string]health.Check{"database": down, "jwt": up}, false, http.StatusServiceUnavailable, "ready-degraded.golden.json"},
		{"draining", nil, true, http.StatusServiceUnavailable, "ready-draining.golden.json"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			readiness := health.NewReadiness(clk, discardLogger())
			for name, check := range tt.checks {
				readiness.Register(name, check)
			}

			if tt.draining {
				readiness.StartDraining()
			}

			rec := serve(t, readiness, "/health/ready")

			assertResponse(t, rec, tt.wantStatus, "application/json", tt.golden)
		})
	}
}

func TestReadinessCheckHasADeadline(t *testing.T) {
	t.Parallel()

	readiness := health.NewReadiness(clk, discardLogger())
	readiness.Register("database", func(ctx context.Context) error {
		if _, ok := ctx.Deadline(); !ok {
			return errors.New("check ran without a deadline")
		}

		return nil
	})

	rec := serve(t, readiness, "/health/ready")

	if rec.Code != http.StatusOK {
		t.Errorf("status = %d, want %d", rec.Code, http.StatusOK)
	}
}

func serve(t *testing.T, h http.Handler, target string) *httptest.ResponseRecorder {
	t.Helper()

	rec := httptest.NewRecorder()
	h.ServeHTTP(rec, httptest.NewRequestWithContext(t.Context(), http.MethodGet, target, http.NoBody))

	return rec
}

func assertResponse(t *testing.T, rec *httptest.ResponseRecorder, wantStatus int, wantType, golden string) {
	t.Helper()

	if rec.Code != wantStatus {
		t.Errorf("status = %d, want %d", rec.Code, wantStatus)
	}

	if got := rec.Header().Get("Content-Type"); got != wantType {
		t.Errorf("Content-Type = %q, want %q", got, wantType)
	}

	body, err := io.ReadAll(rec.Body)
	if err != nil {
		t.Fatalf("read body: %v", err)
	}

	path := filepath.Join("testdata", golden)
	if *update {
		if err := os.WriteFile(path, body, 0o600); err != nil {
			t.Fatalf("write golden file: %v", err)
		}
	}

	want, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("read golden file: %v", err)
	}

	if diff := cmp.Diff(string(want), string(body)); diff != "" {
		t.Errorf("body mismatch with %s (-want +got):\n%s", path, diff)
	}
}

package bootstrap_test

import (
	"context"
	"encoding/json"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/bootstrap"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

type fixedClock struct{ now time.Time }

func (c fixedClock) Now() time.Time { return c.now }

func TestMain(m *testing.M) { testdb.Main(m) }

func newApp(t *testing.T) *bootstrap.App {
	t.Helper()

	app := bootstrap.New(bootstrap.Deps{
		Logger: slog.New(slog.DiscardHandler),
		Clock:  fixedClock{now: time.Date(2026, 10, 7, 9, 30, 0, 0, time.UTC)},
		Pool:   testdb.New(t),
	})
	t.Cleanup(func() {
		if err := app.Close(context.Background()); err != nil {
			t.Errorf("Close() error = %v", err)
		}
	})

	return app
}

func TestRoutes(t *testing.T) {
	t.Parallel()

	tests := []struct {
		method     string
		target     string
		wantStatus int
	}{
		{http.MethodGet, "/health", http.StatusOK},
		{http.MethodHead, "/health", http.StatusOK},
		{http.MethodGet, "/health/ready", http.StatusOK},
		{http.MethodGet, "/up", http.StatusOK},
		{http.MethodPost, "/health", http.StatusMethodNotAllowed},
		{http.MethodGet, "/api/v1/meta", http.StatusNotFound},
	}

	app := newApp(t)

	for _, tt := range tests {
		t.Run(tt.method+" "+tt.target, func(t *testing.T) {
			t.Parallel()

			rec := httptest.NewRecorder()
			app.Handler.ServeHTTP(rec, httptest.NewRequestWithContext(t.Context(), tt.method, tt.target, http.NoBody))

			if rec.Code != tt.wantStatus {
				t.Errorf("status = %d, want %d", rec.Code, tt.wantStatus)
			}
		})
	}
}

func TestReadinessReportsTheDatabase(t *testing.T) {
	t.Parallel()

	app := newApp(t)

	status, body := readiness(t, app)
	if status != http.StatusOK || body.Components["database"] != "up" {
		t.Errorf("before Close: %d %+v, want 200 with database up", status, body)
	}

	// Close releases the pool, so the check now fails.
	if err := app.Close(t.Context()); err != nil {
		t.Fatalf("Close() error = %v", err)
	}

	status, body = readiness(t, app)
	if status != http.StatusServiceUnavailable || body.Components["database"] != "down" {
		t.Errorf("after Close: %d %+v, want 503 with database down", status, body)
	}
}

type readinessBody struct {
	Status     string            `json:"status"`
	Components map[string]string `json:"components"`
}

func readiness(t *testing.T, app *bootstrap.App) (int, readinessBody) {
	t.Helper()

	rec := httptest.NewRecorder()
	app.Handler.ServeHTTP(rec, httptest.NewRequestWithContext(t.Context(), http.MethodGet, "/health/ready", http.NoBody))

	var body readinessBody
	if err := json.NewDecoder(rec.Body).Decode(&body); err != nil {
		t.Fatalf("decode: %v", err)
	}

	return rec.Code, body
}

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
)

type fixedClock struct{ now time.Time }

func (c fixedClock) Now() time.Time { return c.now }

func newApp(t *testing.T) *bootstrap.App {
	t.Helper()

	app := bootstrap.New(bootstrap.Deps{
		Logger: slog.New(slog.DiscardHandler),
		Clock:  fixedClock{now: time.Date(2026, 10, 7, 9, 30, 0, 0, time.UTC)},
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

func TestReadinessStartsWithNoComponents(t *testing.T) {
	t.Parallel()

	rec := httptest.NewRecorder()
	newApp(t).Handler.ServeHTTP(rec, httptest.NewRequestWithContext(t.Context(), http.MethodGet, "/health/ready", http.NoBody))

	var body struct {
		Status     string            `json:"status"`
		Components map[string]string `json:"components"`
	}
	if err := json.NewDecoder(rec.Body).Decode(&body); err != nil {
		t.Fatalf("decode: %v", err)
	}

	if body.Status != "ok" || body.Components == nil || len(body.Components) != 0 {
		t.Errorf("body = %+v, want status ok and an empty components map", body)
	}
}

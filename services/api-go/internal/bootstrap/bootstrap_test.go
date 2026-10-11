package bootstrap_test

import (
	"context"
	"log/slog"
	"net/http"
	"strings"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/bootstrap"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

type fixedClock struct{ now time.Time }

func (c fixedClock) Now() time.Time { return c.now }

func TestMain(m *testing.M) { testdb.Main(m) }

func newApp(t *testing.T) *bootstrap.App {
	t.Helper()

	app, err := bootstrap.New(bootstrap.Deps{
		Config: config.Config{AppURL: "http://localhost:8080", RateLimitPerMinute: 100_000},
		Logger: slog.New(slog.DiscardHandler),
		Clock:  fixedClock{now: time.Date(2026, 10, 7, 9, 30, 0, 0, time.UTC)},
		Pool:   testdb.New(t),
	})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}

	t.Cleanup(func() {
		if err := app.Close(context.Background()); err != nil {
			t.Errorf("Close() error = %v", err)
		}
	})

	return app
}

func TestRoutes(t *testing.T) {
	t.Parallel()

	api := apitest.New(t, newApp(t).Handler)

	tests := []struct {
		method     string
		path       string
		wantStatus int
	}{
		{http.MethodGet, "/health", http.StatusOK},
		{http.MethodHead, "/health", http.StatusOK},
		{http.MethodGet, "/health/ready", http.StatusOK},
		{http.MethodGet, "/up", http.StatusOK},
		{http.MethodGet, "/api/v1/meta", http.StatusOK},
		{http.MethodPost, "/health", http.StatusMethodNotAllowed},
		{http.MethodGet, "/api/v1/does-not-exist", http.StatusNotFound},
	}

	for _, tt := range tests {
		t.Run(tt.method+" "+tt.path, func(t *testing.T) {
			t.Parallel()

			api.Do(apitest.Request{Method: tt.method, Path: tt.path}).
				AssertStatus(tt.wantStatus).
				AssertHeader("X-Request-Id", apitest.RequestID)
		})
	}
}

// The body is the PHP API's response, copied verbatim into the golden file.
func TestMetaMatchesThePHPAPI(t *testing.T) {
	t.Parallel()

	apitest.New(t, newApp(t).Handler).
		Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/meta", Locale: "ru"}).
		AssertStatus(http.StatusOK).
		AssertHeader("Cache-Control", "public, max-age=3600").
		AssertHeader("Content-Type", "application/json").
		AssertGolden("meta.golden.json")
}

func TestLocaleNegotiation(t *testing.T) {
	t.Parallel()

	api := apitest.New(t, newApp(t).Handler)

	for header, want := range map[string]string{
		"":                        "en",
		"ru":                      "ru",
		"ru-RU,ru;q=0.9,en;q=0.5": "ru",
		"de":                      "en",
		"fr-FR,de;q=0.8":          "en",
	} {
		res := api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/meta", Locale: header})
		res.AssertHeader("Content-Language", want)

		if vary := res.Header().Values("Vary"); !contains(vary, "Accept-Language") {
			t.Errorf("Accept-Language %q: Vary = %q, want Accept-Language", header, vary)
		}
	}
}

func TestUnknownRouteIsANotFoundProblem(t *testing.T) {
	t.Parallel()

	res := apitest.New(t, newApp(t).Handler).
		Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/does-not-exist"}).
		AssertStatus(http.StatusNotFound).
		AssertHeader("Content-Type", "application/problem+json").
		AssertGolden("problem-not-found.golden.json")

	if got := res.Problem().Instance; got == nil || *got != "/api/v1/does-not-exist#"+apitest.RequestID {
		t.Errorf("instance = %v, want the path plus the request id", got)
	}
}

func TestWrongMethodIsAMethodNotAllowedProblem(t *testing.T) {
	t.Parallel()

	res := apitest.New(t, newApp(t).Handler).
		Do(apitest.Request{Method: http.MethodDelete, Path: "/api/v1/meta"}).
		AssertStatus(http.StatusMethodNotAllowed).
		AssertHeader("Allow", "GET, HEAD")

	if p := res.Problem(); p.Code != "method_not_allowed" || p.Title != "Method not allowed" {
		t.Errorf("problem = %+v, want method_not_allowed", p)
	}
}

func TestMalformedJSONIsAValidationProblem(t *testing.T) {
	t.Parallel()

	p := apitest.New(t, newApp(t).Handler).
		Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/login", Body: `{"email": "ada@example.com", bad`}).
		AssertStatus(http.StatusUnprocessableEntity).
		Problem()

	if len(p.Errors) != 1 || p.Errors[0].Pointer != "" || p.Errors[0].Code != gen.FieldErrorCodeInvalidFormat {
		t.Errorf("errors = %+v, want one invalid_format error on the whole body", p.Errors)
	}
}

// Request validation runs before any handler, so it covers operations no
// story has implemented yet.
func TestRequestShapeErrorsAreLocalizedPointerErrors(t *testing.T) {
	t.Parallel()

	apitest.New(t, newApp(t).Handler).
		Do(apitest.Request{
			Method: http.MethodPost,
			Path:   "/api/v1/auth/login",
			Locale: "ru",
			Body:   map[string]any{"email": 5, "client": "toaster", "extra": 1},
		}).
		AssertStatus(http.StatusUnprocessableEntity).
		AssertGolden("problem-validation-ru.golden.json")
}

func TestParameterErrors(t *testing.T) {
	t.Parallel()

	api := apitest.New(t, newApp(t).Handler)

	// A non-numeric id is a 404, as the PHP API constrains id routes to digits.
	if p := api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/workouts/abc"}).
		AssertStatus(http.StatusNotFound).Problem(); p.Code != "not_found" {
		t.Errorf("non-numeric id: code = %q, want not_found", p.Code)
	}

	p := api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/workouts?per_page=500"}).
		AssertStatus(http.StatusUnprocessableEntity).Problem()

	want := []string{"/per_page out_of_range"}
	got := make([]string, 0, len(p.Errors))

	for _, e := range p.Errors {
		got = append(got, e.Pointer+" "+string(e.Code))
	}

	if diff := cmp.Diff(want, got); diff != "" {
		t.Errorf("errors (-want +got):\n%s", diff)
	}
}

func TestUnimplementedOperationsAnswer501(t *testing.T) {
	t.Parallel()

	p := apitest.New(t, newApp(t).Handler).
		Do(apitest.Request{Method: http.MethodGet, Path: "/api/v1/programs", SkipContract: true}).
		AssertStatus(http.StatusNotImplemented).
		Problem()

	if p.Code != "internal_error" {
		t.Errorf("code = %q, want internal_error", p.Code)
	}
}

func TestReadinessReportsTheDatabase(t *testing.T) {
	t.Parallel()

	app := newApp(t)
	api := apitest.New(t, app.Handler)

	api.Do(apitest.Request{Method: http.MethodGet, Path: "/health/ready"}).
		AssertStatus(http.StatusOK).
		AssertGolden("ready-database-up.golden.json")

	// Close releases the pool, so the check now fails.
	if err := app.Close(t.Context()); err != nil {
		t.Fatalf("Close() error = %v", err)
	}

	api.Do(apitest.Request{Method: http.MethodGet, Path: "/health/ready"}).
		AssertStatus(http.StatusServiceUnavailable).
		AssertGolden("ready-database-down.golden.json")
}

func contains(values []string, want string) bool {
	for _, v := range values {
		for _, part := range strings.Split(v, ",") {
			if strings.TrimSpace(part) == want {
				return true
			}
		}
	}

	return false
}

package bootstrap_test

import (
	"bytes"
	"context"
	"encoding/json"
	"log/slog"
	"net/http"
	"strings"
	"testing"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"
	sdktrace "go.opentelemetry.io/otel/sdk/trace"
	"go.opentelemetry.io/otel/sdk/trace/tracetest"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/bootstrap"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/observability"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

// flushProbe records spans and, on shutdown, whether the pool still
// answered: the telemetry must be flushed before the pool closes.
type flushProbe struct {
	*tracetest.InMemoryExporter

	pool          *pgxpool.Pool
	poolAtFlush   error
	shutdownCalls int
}

func (p *flushProbe) Shutdown(ctx context.Context) error {
	p.shutdownCalls++
	p.poolAtFlush = p.pool.Ping(ctx)

	return p.InMemoryExporter.Shutdown(ctx)
}

var _ sdktrace.SpanExporter = (*flushProbe)(nil)

func TestARequestsLogLinesCarryItsIdsAndTrace(t *testing.T) {
	t.Parallel()

	pool := testdb.New(t)
	probe := &flushProbe{InMemoryExporter: tracetest.NewInMemoryExporter(), pool: pool}

	telemetry, err := observability.New(t.Context(), observability.Config{ServiceName: "api-go", SpanExporter: probe})
	if err != nil {
		t.Fatal(err)
	}

	var logs bytes.Buffer

	app, err := bootstrap.New(bootstrap.Deps{
		Config: config.Config{
			AppURL: "http://localhost:8080", RateLimitPerMinute: 100_000, BcryptRounds: 4, JWT: testJWT,
			AppName: "My Workouts", FrontendURL: "https://app.example.test", LinkSigningKey: "test-link-signing-key",
		},
		Logger:    slog.New(slog.NewJSONHandler(&logs, nil)),
		Clock:     fixedClock{now: time.Date(2026, 10, 7, 9, 30, 0, 0, time.UTC)},
		Pool:      pool,
		Mailer:    &mail.Fake{},
		Telemetry: telemetry,
	})
	if err != nil {
		t.Fatal(err)
	}

	api := apitest.New(t, app.Handler)

	res := api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/v1/auth/register", Body: map[string]any{
		"name": "Ada", "email": "ada@example.com", "password": "secret123", "client": "native",
	}}).AssertStatus(http.StatusCreated)

	var auth struct {
		AccessToken string `json:"access_token"`
		User        struct {
			ID int64 `json:"id"`
		} `json:"user"`
	}
	if err := json.Unmarshal(res.Body.Bytes(), &auth); err != nil {
		t.Fatal(err)
	}

	const meRequest = "00000000-0000-4000-8000-0000000000aa"

	api.Do(apitest.Request{
		Method: http.MethodGet, Path: "/api/v1/me", Token: auth.AccessToken,
		Header: http.Header{"X-Request-Id": {meRequest}},
	}).AssertStatus(http.StatusOK)

	traces := map[string]string{} // span name → trace id
	for _, span := range probe.GetSpans() {
		traces[span.Name] = span.SpanContext.TraceID().String()
	}

	registered := findLine(t, &logs, func(l map[string]any) bool { return l["msg"] == "user.registered" })
	if registered["request_id"] != apitest.RequestID || registered["client"] != "native" ||
		registered["trace_id"] != traces["POST /api/v1/auth/register"] {
		t.Errorf("user.registered = %v, want the request id, the client and the register span's trace", registered)
	}

	me := findLine(t, &logs, func(l map[string]any) bool { return l["msg"] == "request" && l["request_id"] == meRequest })
	if me["user_id"] != float64(auth.User.ID) || me["route"] != "GET /api/v1/me" || me["trace_id"] != traces["GET /api/v1/me"] {
		t.Errorf("access log = %v, want user %d and the /me span's trace %s", me, auth.User.ID, traces["GET /api/v1/me"])
	}

	if err := app.Close(context.Background()); err != nil {
		t.Fatal(err)
	}

	if probe.shutdownCalls != 1 || probe.poolAtFlush != nil {
		t.Errorf("exporter shut down %d times, pool at flush: %v; want once, with the pool open", probe.shutdownCalls, probe.poolAtFlush)
	}
}

func findLine(t *testing.T, logs *bytes.Buffer, match func(map[string]any) bool) map[string]any {
	t.Helper()

	for raw := range strings.SplitSeq(strings.TrimSpace(logs.String()), "\n") {
		var line map[string]any
		if json.Unmarshal([]byte(raw), &line) == nil && match(line) {
			return line
		}
	}

	t.Fatalf("no matching log line in:\n%s", logs)

	return nil
}

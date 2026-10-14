package httpapi_test

import (
	"bytes"
	"context"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"go.opentelemetry.io/otel/attribute"
	"go.opentelemetry.io/otel/sdk/trace/tracetest"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/observability"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/ratelimit"
)

type telemetryFixture struct {
	handler   http.Handler
	spans     *tracetest.InMemoryExporter
	telemetry *observability.Telemetry
	logs      *bytes.Buffer
}

func newTelemetryFixture(t testing.TB) *telemetryFixture {
	t.Helper()

	bundle, err := i18n.NewBundle()
	if err != nil {
		t.Fatal(err)
	}

	f := &telemetryFixture{spans: tracetest.NewInMemoryExporter(), logs: &bytes.Buffer{}}

	f.telemetry, err = observability.New(t.Context(), observability.Config{ServiceName: "api-go", SpanExporter: f.spans})
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = f.telemetry.Close(context.Background()) })

	clk := clock.NewFake(time.Date(2026, 10, 11, 9, 0, 0, 0, time.UTC))

	server, err := httpapi.NewServer(httpapi.Options{
		AppURL: "https://my-workouts.online/", RateLimitPerMinute: 1000, Bundle: bundle,
		Logger: slog.New(slog.NewJSONHandler(f.logs, nil)), Clock: clk, Limiter: ratelimit.NewMemory(clk),
		TracerProvider: f.telemetry.TracerProvider(), MeterProvider: f.telemetry.MeterProvider(),
	})
	if err != nil {
		t.Fatal(err)
	}

	server.Handle("GET /api/items/{itemId}", http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		httpapi.Logger(r.Context()).InfoContext(r.Context(), "item.read", "item_id", r.PathValue("itemId"))
		server.Responder().JSON(w, http.StatusOK, map[string]string{"id": r.PathValue("itemId")})
	}))
	server.Handle("GET /health", http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) { w.WriteHeader(http.StatusOK) }))

	f.handler = server.Handler()

	return f
}

func (f *telemetryFixture) get(t *testing.T, path string, header http.Header) {
	t.Helper()

	r := httptest.NewRequestWithContext(t.Context(), http.MethodGet, path, http.NoBody)
	r.Header = header
	f.handler.ServeHTTP(httptest.NewRecorder(), r)
}

func TestARequestIsTracedByItsRoute(t *testing.T) {
	t.Parallel()

	f := newTelemetryFixture(t)
	f.get(t, "/api/items/42", http.Header{"X-Request-Id": {requestID}})

	spans := f.spans.GetSpans()
	if len(spans) != 1 {
		t.Fatalf("exported %d spans, want 1", len(spans))
	}

	span := spans[0]
	if span.Name != "GET /api/items/{itemId}" {
		t.Errorf("span name = %q, want the route pattern, never the raw path", span.Name)
	}

	if !hasAttribute(span.Attributes, attribute.String("http.route", "/api/items/{itemId}")) {
		t.Errorf("span attributes %v lack http.route", span.Attributes)
	}

	traceID := span.SpanContext.TraceID().String()

	for _, msg := range []string{"item.read", "request"} {
		line := logLine(t, f.logs, msg)
		if line["request_id"] != requestID || line["trace_id"] != traceID {
			t.Errorf("%s log = %v, want request %s and trace %s", msg, line, requestID, traceID)
		}
	}
}

func TestAnIncomingTraceIsContinued(t *testing.T) {
	t.Parallel()

	f := newTelemetryFixture(t)
	f.get(t, "/api/items/42", http.Header{"Traceparent": {"00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01"}})

	if spans := f.spans.GetSpans(); len(spans) != 1 || spans[0].SpanContext.TraceID().String() != "4bf92f3577b34da6a3ce929d0e0e4736" {
		t.Errorf("spans = %v, want one in the caller's trace", spans)
	}
}

func TestProbesAreNotTraced(t *testing.T) {
	t.Parallel()

	f := newTelemetryFixture(t)
	f.get(t, "/health", http.Header{})

	if spans := f.spans.GetSpans(); len(spans) != 0 {
		t.Errorf("traced the probe: %v", spans)
	}
}

func TestRequestMetricsAreLabelledByRoute(t *testing.T) {
	t.Parallel()

	f := newTelemetryFixture(t)
	f.get(t, "/api/items/1", http.Header{})
	f.get(t, "/api/items/2", http.Header{})
	f.get(t, "/nowhere/3", http.Header{})

	rec := httptest.NewRecorder()
	f.telemetry.MetricsHandler().ServeHTTP(rec, httptest.NewRequestWithContext(t.Context(), http.MethodGet, "/metrics", http.NoBody))
	metrics := rec.Body.String()

	for _, want := range []string{
		`http_server_requests_total{method="GET",route="/api/items/{itemId}",status="200"} 2`,
		`http_server_requests_total{method="GET",route="unmatched",status="404"} 1`,
		`http_server_request_duration_seconds_bucket{method="GET",route="/api/items/{itemId}",status="200",le="0.005"} 2`,
		`http_server_request_duration_seconds_count{method="GET",route="/api/items/{itemId}",status="200"} 2`,
	} {
		if !strings.Contains(metrics, want) {
			t.Errorf("metrics lack %s", want)
		}
	}

	if strings.Contains(metrics, "/api/items/1") {
		t.Error("a raw path leaked into a label")
	}
}

func hasAttribute(attrs []attribute.KeyValue, want attribute.KeyValue) bool {
	for _, attr := range attrs {
		if attr == want {
			return true
		}
	}

	return false
}

// BenchmarkRequest measures one request through the whole chain, telemetry
// included: `go test -run '^$' -bench Request -benchmem ./internal/platform/httpapi/`.
// It informs, it does not gate.
func BenchmarkRequest(b *testing.B) {
	f := newTelemetryFixture(b)
	r := httptest.NewRequestWithContext(b.Context(), http.MethodGet, "/api/items/42", http.NoBody)

	b.ReportAllocs()

	for b.Loop() {
		f.handler.ServeHTTP(httptest.NewRecorder(), r)
	}
}

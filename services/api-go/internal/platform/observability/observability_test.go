package observability_test

import (
	"bytes"
	"context"
	"encoding/json"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync/atomic"
	"testing"

	dto "github.com/prometheus/client_model/go"
	"github.com/prometheus/common/expfmt"
	"github.com/prometheus/common/model"
	"go.opentelemetry.io/otel/sdk/trace/tracetest"
	"go.uber.org/goleak"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/observability"
)

// Not parallel: goleak compares the process's goroutines.
func TestWithoutAnEndpointNoExporterGoroutineRuns(t *testing.T) {
	ignore := goleak.IgnoreCurrent()

	telemetry, err := observability.New(t.Context(), observability.Config{ServiceName: "api-go"})
	if err != nil {
		t.Fatal(err)
	}

	_, span := telemetry.TracerProvider().Tracer("test").Start(t.Context(), "work")
	span.End()

	if err := goleak.Find(ignore); err != nil {
		t.Errorf("goroutines started without an OTLP endpoint: %v", err)
	}

	if err := telemetry.Close(t.Context()); err != nil {
		t.Fatal(err)
	}
}

func TestWithAnEndpointSpansAreExportedOnClose(t *testing.T) {
	var exports atomic.Int32

	collector := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/v1/traces" {
			exports.Add(1)
		}

		w.WriteHeader(http.StatusOK)
	}))
	t.Cleanup(collector.Close)

	telemetry, err := observability.New(t.Context(), observability.Config{ServiceName: "api-go", OTLPEndpoint: collector.URL})
	if err != nil {
		t.Fatal(err)
	}

	_, span := telemetry.TracerProvider().Tracer("test").Start(t.Context(), "work")
	span.End()

	if err := telemetry.Close(t.Context()); err != nil {
		t.Fatal(err)
	}

	if exports.Load() == 0 {
		t.Error("Close() did not flush the span to {endpoint}/v1/traces")
	}
}

func TestMetricsAreServedInThePrometheusFormat(t *testing.T) {
	t.Parallel()

	telemetry, err := observability.New(t.Context(), observability.Config{ServiceName: "api-go", Version: "1.2.3", Environment: "testing"})
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = telemetry.Close(context.Background()) })

	counter, err := telemetry.MeterProvider().Meter("test").Int64Counter("jobs.failed")
	if err != nil {
		t.Fatal(err)
	}

	counter.Add(t.Context(), 2)

	families := scrape(t, telemetry)

	if got := families["jobs_failed_total"]; got == nil || got.GetMetric()[0].GetCounter().GetValue() != 2 {
		t.Errorf("jobs_failed_total = %v", got)
	}

	if families["go_goroutine_count"] == nil && families["go_goroutines"] == nil {
		t.Errorf("no runtime metrics among %d families", len(families))
	}

	info := families["target_info"]
	if info == nil || !hasLabel(info, "service_name", "api-go") || !hasLabel(info, "service_version", "1.2.3") ||
		!hasLabel(info, "deployment_environment", "testing") {
		t.Errorf("target_info = %v, want the service resource", info)
	}
}

type dtoFamily = dto.MetricFamily

func scrape(t *testing.T, telemetry *observability.Telemetry) map[string]*dtoFamily {
	t.Helper()

	rec := httptest.NewRecorder()
	telemetry.MetricsHandler().ServeHTTP(rec, httptest.NewRequestWithContext(t.Context(), http.MethodGet, "/metrics", http.NoBody))

	parser := expfmt.NewTextParser(model.UTF8Validation)

	families, err := parser.TextToMetricFamilies(rec.Body)
	if err != nil {
		t.Fatalf("not Prometheus text: %v\n%s", err, rec.Body)
	}

	return families
}

func hasLabel(family *dtoFamily, name, value string) bool {
	for _, metric := range family.GetMetric() {
		for _, label := range metric.GetLabel() {
			if label.GetName() == name && label.GetValue() == value {
				return true
			}
		}
	}

	return false
}

func TestLogLinesCarryTheContextFieldsAndTraceIDs(t *testing.T) {
	t.Parallel()

	exporter := tracetest.NewInMemoryExporter()

	telemetry, err := observability.New(t.Context(), observability.Config{ServiceName: "api-go", SpanExporter: exporter})
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = telemetry.Close(context.Background()) })

	var buf bytes.Buffer
	logger := observability.Logger(slog.New(slog.NewJSONHandler(&buf, nil)))

	if observability.Logger(logger) != logger {
		t.Error("Logger() wrapped an already wrapped logger again")
	}

	outer := observability.WithLogFields(t.Context(), slog.String("request_id", "req-1"))
	ctx, span := telemetry.TracerProvider().Tracer("test").Start(outer, "GET /api/v1/me")

	// An inner layer adds the user; the outer context sees it too.
	observability.AddLogFields(ctx, slog.Int64("user_id", 7))
	logger.InfoContext(ctx, "auth.login", "client", "web")
	span.End()
	logger.InfoContext(outer, "request")
	logger.Info("no context")

	lines := decodeLines(t, &buf)
	traceID := exporter.GetSpans()[0].SpanContext.TraceID().String()

	if line := lines[0]; line["request_id"] != "req-1" || line["user_id"] != float64(7) || line["client"] != "web" ||
		line["trace_id"] != traceID || line["span_id"] == nil {
		t.Errorf("event line = %v, want the fields and trace %s", line, traceID)
	}

	if line := lines[1]; line["user_id"] != float64(7) || line["trace_id"] != nil {
		t.Errorf("outer line = %v, want the user and no span", line)
	}

	if line := lines[2]; line["request_id"] != nil {
		t.Errorf("line without a context = %v", line)
	}

	// Fields added without a holder are dropped, not panicked on.
	observability.AddLogFields(t.Context(), slog.String("lost", "x"))
}

func TestLogHandlerKeepsAttrsAndGroups(t *testing.T) {
	t.Parallel()

	var buf bytes.Buffer
	logger := slog.New(observability.NewLogHandler(slog.NewJSONHandler(&buf, &slog.HandlerOptions{Level: slog.LevelWarn}))).
		With("service", "api-go").WithGroup("river")

	ctx := observability.WithLogFields(t.Context(), slog.String("request_id", "req-1"))
	logger.InfoContext(ctx, "dropped")
	logger.WarnContext(ctx, "kept", "queue", "default")

	if got := strings.TrimSpace(buf.String()); !strings.Contains(got, `"service":"api-go","river":{"queue":"default","request_id":"req-1"}`) || strings.Contains(got, "dropped") {
		t.Errorf("log = %s", got)
	}
}

func TestQueryTracersLogQueriesOnlyAtDebug(t *testing.T) {
	t.Parallel()

	telemetry, err := observability.New(t.Context(), observability.Config{ServiceName: "api-go"})
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = telemetry.Close(context.Background()) })

	info := slog.New(slog.NewJSONHandler(io.Discard, nil))
	debug := slog.New(slog.NewJSONHandler(io.Discard, &slog.HandlerOptions{Level: slog.LevelDebug}))

	if got := len(telemetry.QueryTracers(t.Context(), info)); got != 1 {
		t.Errorf("at info: %d tracers, want only otelpgx", got)
	}

	if got := len(telemetry.QueryTracers(t.Context(), debug)); got != 2 {
		t.Errorf("at debug: %d tracers, want otelpgx and the query log", got)
	}
}

func decodeLines(t *testing.T, buf *bytes.Buffer) []map[string]any {
	t.Helper()

	var lines []map[string]any

	for raw := range strings.SplitSeq(strings.TrimSpace(buf.String()), "\n") {
		var line map[string]any
		if err := json.Unmarshal([]byte(raw), &line); err != nil {
			t.Fatalf("not JSON: %s", raw)
		}

		lines = append(lines, line)
	}

	return lines
}

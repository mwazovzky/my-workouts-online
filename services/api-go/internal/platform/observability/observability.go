// Package observability is the service's telemetry: OpenTelemetry tracer
// and meter providers with the service's resource, a Prometheus registry
// the meters export to (scraped at /metrics on the admin listener), an
// OTLP/HTTP trace exporter switched on by OTEL_EXPORTER_OTLP_ENDPOINT, and a
// slog handler that stamps every line with the request's fields and trace
// ids (log.go). Without an endpoint, spans are still created, so logs carry
// trace ids, but nothing leaves the process and no exporter goroutine runs.
package observability

import (
	"context"
	"errors"
	"fmt"
	"net/http"
	"net/url"

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"go.opentelemetry.io/contrib/instrumentation/runtime"
	"go.opentelemetry.io/otel/attribute"
	"go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracehttp"
	otelprom "go.opentelemetry.io/otel/exporters/prometheus"
	"go.opentelemetry.io/otel/metric"
	sdkmetric "go.opentelemetry.io/otel/sdk/metric"
	"go.opentelemetry.io/otel/sdk/resource"
	sdktrace "go.opentelemetry.io/otel/sdk/trace"
	"go.opentelemetry.io/otel/trace"
)

// Config configures the telemetry.
type Config struct {
	// ServiceName is service.name: api-go or worker-go.
	ServiceName string
	// Version is service.version, the build's version.
	Version string
	// Environment is deployment.environment (APP_ENV).
	Environment string
	// OTLPEndpoint is OTEL_EXPORTER_OTLP_ENDPOINT, the collector's base URL;
	// traces go to {OTLPEndpoint}/v1/traces. Empty exports nothing.
	OTLPEndpoint string
	// SpanExporter, when set, receives every span synchronously instead of
	// the OTLP exporter (tests pass tracetest.InMemoryExporter).
	SpanExporter sdktrace.SpanExporter
}

// Telemetry holds the providers. Close it after the servers stop and before
// the database pool closes, so the last spans and metrics are flushed.
type Telemetry struct {
	tracerProvider *sdktrace.TracerProvider
	meterProvider  *sdkmetric.MeterProvider
	registry       *prometheus.Registry
}

// New builds the providers. It starts the runtime metrics and, with an
// OTLP endpoint, the batching span exporter.
func New(ctx context.Context, cfg Config) (*Telemetry, error) {
	res := resource.NewSchemaless(
		attribute.String("service.name", cfg.ServiceName),
		attribute.String("service.version", cfg.Version),
		attribute.String("deployment.environment", cfg.Environment),
	)

	registry := prometheus.NewRegistry()

	exporter, err := otelprom.New(otelprom.WithRegisterer(registry), otelprom.WithoutScopeInfo())
	if err != nil {
		return nil, fmt.Errorf("observability: prometheus exporter: %w", err)
	}

	meterProvider := sdkmetric.NewMeterProvider(sdkmetric.WithResource(res), sdkmetric.WithReader(exporter))

	if err := runtime.Start(runtime.WithMeterProvider(meterProvider)); err != nil {
		return nil, fmt.Errorf("observability: runtime metrics: %w", err)
	}

	traceOptions := []sdktrace.TracerProviderOption{sdktrace.WithResource(res)}

	switch {
	case cfg.SpanExporter != nil:
		traceOptions = append(traceOptions, sdktrace.WithSyncer(cfg.SpanExporter))
	case cfg.OTLPEndpoint != "":
		endpoint, err := url.Parse(cfg.OTLPEndpoint)
		if err != nil {
			return nil, fmt.Errorf("observability: OTEL_EXPORTER_OTLP_ENDPOINT: %w", err)
		}

		// The base endpoint gets the signal's path, as the OTel spec defines
		// for OTEL_EXPORTER_OTLP_ENDPOINT.
		spanExporter, err := otlptracehttp.New(ctx, otlptracehttp.WithEndpointURL(endpoint.JoinPath("v1", "traces").String()))
		if err != nil {
			return nil, fmt.Errorf("observability: otlp exporter: %w", err)
		}

		traceOptions = append(traceOptions, sdktrace.WithBatcher(spanExporter))
	}

	return &Telemetry{
		tracerProvider: sdktrace.NewTracerProvider(traceOptions...),
		meterProvider:  meterProvider,
		registry:       registry,
	}, nil
}

// TracerProvider returns the tracer provider.
func (t *Telemetry) TracerProvider() trace.TracerProvider { return t.tracerProvider }

// MeterProvider returns the meter provider.
func (t *Telemetry) MeterProvider() metric.MeterProvider { return t.meterProvider }

// MetricsHandler serves the metrics in the Prometheus text format.
func (t *Telemetry) MetricsHandler() http.Handler {
	return promhttp.HandlerFor(t.registry, promhttp.HandlerOpts{})
}

// Close flushes and stops the exporters: spans first, then metrics.
func (t *Telemetry) Close(ctx context.Context) error {
	var errs []error
	for _, shutdown := range []func(context.Context) error{t.tracerProvider.Shutdown, t.meterProvider.Shutdown} {
		if err := shutdown(ctx); err != nil {
			errs = append(errs, err)
		}
	}

	if err := errors.Join(errs...); err != nil {
		return fmt.Errorf("observability: shutdown: %w", err)
	}

	return nil
}

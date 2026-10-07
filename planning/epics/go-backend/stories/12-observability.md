# User Story: Observability

Add traces, metrics and correlated logs to the Go service so its behaviour in dev and production can be seen without attaching a debugger.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: none
- Creates New Feature: No

## Problem Statement

The PHP service ships JSON logs to Loki through Alloy, with `request_id`, `user_id`, `method`, `route_name` and `route_uri` on every line (`app/Http/Middleware/LogContext.php`) and named business events (`auth.login`, `user.registered`, `workout.started`, `program.enrolled`, readiness warnings). It has no metrics and no tracing. The Go service already logs JSON with `request_id` (story 3) but nothing measures latency, error rate, database time or queue depth, and nothing correlates a log line with a request span. Before the backend switch (story 13) can be operated, the two SLIs the epic promises, p99 latency and 5xx rate, must be observable.

## User-Visible Outcome

None. Operators get traces, a `/metrics` endpoint and logs that match the PHP field names, so one Grafana dashboard serves both backends.

## Scope

- `internal/platform/observability`: OpenTelemetry SDK setup with a resource (`service.name=api-go`, `service.version` from the build, `deployment.environment`), a Prometheus metrics exporter served on `ADMIN_ADDR` at `/metrics` (admin listener also hosts `/health/ready`; nginx never proxies it), and an OTLP trace exporter enabled only when `OTEL_EXPORTER_OTLP_ENDPOINT` is set; `shutdown` flushes exporters inside the graceful-shutdown sequence.
- Instrumentation: `otelhttp.NewHandler` around the mux with the route pattern as span name (not the raw path); `otelpgx` tracer on the pool; `go.opentelemetry.io/contrib/instrumentation/runtime` for GC and goroutine metrics; River's built-in metrics hooked to the same meter provider.
- Metrics (names stable, labelled by `route` and `status`): `http_server_request_duration_seconds` histogram, `http_server_requests_total`, `db_client_operation_duration_seconds`, `jobs_active`, `jobs_failed_total`; the two SLIs are documented as PromQL: p99 of the duration histogram, and `sum(rate(...{status=~"5.."}))/sum(rate(...))`.
- Logging: the request logger (story 3) gains `trace_id` and `span_id` from the span context; `user_id` after the bearer middleware; `route` = mux pattern; business events emitted with the PHP names and fields (`auth.login` with `client`, `auth.logout`, `auth.password_reset`, `user.registered`, `user.email_verified`, `workout.started`, `workout.completed`, `program.enrolled`); readiness failures log one warning per component as PHP does.
- `pgx/tracelog` wired to slog at `debug` level, off by default (`LOG_LEVEL`).
- Alloy: no config change required (it tails container stderr by compose service name); `docker/alloy/config.alloy` `keep` regex extended from `api|web|worker` to include `api-go|worker-go`.
- `docs/deployment.md` "Logs and Grafana" gains a Go subsection: fields, the `/metrics` endpoint, how to scrape it from Alloy later, and the two SLI queries.

## Non-Goals

- shipping metrics or traces to Grafana Cloud from production (the exporter switch exists; wiring Alloy's OTLP receiver is a follow-up)
- dashboards as code
- PHP instrumentation

## Acceptance Criteria

- `curl localhost:9090/metrics` on the dev stack lists the five metric families with `route` labels equal to mux patterns such as `/api/v1/workouts/{workoutId}`
- a request with `X-Request-Id: <uuid>` produces log lines whose `request_id` equals it and whose `trace_id` matches the span exported to an in-memory exporter in tests
- `jobs_failed_total` increments when the story-11 worker exhausts retries in a test
- with `OTEL_EXPORTER_OTLP_ENDPOINT` unset no exporter goroutine is started (verified with `goleak`)
- shutdown flushes the exporters before the pool closes
- `make check` green; the instrumentation adds no allocations on the hot path beyond the OTel handler itself (benchmark in CI as information, not a gate)

## Initial Tasks

- [ ] `observability` package: resource, meter and tracer providers, Prometheus and OTLP exporters, shutdown
- [ ] `otelhttp`, `otelpgx`, runtime and River instrumentation in `bootstrap`
- [ ] log enrichment with trace ids and the PHP event names
- [ ] admin listener serving `/metrics` and `/health/ready`
- [ ] Alloy `keep` regex, `docs/deployment.md`
- [ ] tests with the in-memory span exporter and a Prometheus registry scrape

## Test Cases

- unit: span name uses the route pattern; log record carries `trace_id`, `span_id`, `request_id`, `user_id`
- integration: metrics endpoint scrape parses with the Prometheus text parser and contains the expected families and labels
- lifecycle: exporter shutdown order; no goroutine leak when OTLP is disabled

## Docs To Update

- `docs/deployment.md` (Logs and Grafana: Go section, SLI queries, `/metrics`)
- `docs/architecture.md` (observability layer)

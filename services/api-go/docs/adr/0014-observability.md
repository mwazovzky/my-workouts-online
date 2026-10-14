# 0014. Observability: OpenTelemetry, Prometheus Metrics and Request-Scoped Logs

- Status: accepted
- Date: 2026-10-13
- Story: [Go epic story 12](../../../../planning/epics/go-backend/stories/12-observability.md)

## Context

The PHP API ships JSON logs to Loki through Alloy, with `request_id`, `user_id`, `method` and the route on every line (`LogContext`), and logs named business events (`auth.login`, `workout.started`, …). It has no metrics and no tracing. Before the backend switch (story 13), the Go API must expose the two SLIs the epic promises, p99 latency and the 5xx rate, and tie a log line to its request and trace.

Constraints found while building it:

- The middleware chain sees the route only after the `ServeMux` matches. A tracing handler wrapping the chain starts its span before that, so it cannot name the span by route on its own.
- `otelhttp`'s metrics carry semantic-convention labels (`http_request_method`, `http_response_status_code`) and record under the same name as the SLI histogram the story specifies (`http_server_request_duration_seconds`, labelled `route` and `status`). Two instruments with one name would conflict in the Prometheus registry.
- River's core has no metrics; `rivercontrib/otelriver` is a separate module whose names differ from the story's.
- The business events were logged with the base logger, so they carried no `request_id`; `auth.login` and `user.registered` lacked PHP's `client`.

## Considered Options

1. `otelhttp` for traces and metrics with its semantic-convention names, accepting different metric and label names.
2. `otelhttp` for traces only, its meter set to a no-op. The access-log middleware, which already knows route, status and duration, records the request metrics.
3. A request-scoped logger passed through the context (`logger.With("request_id", …)`), which use cases do not receive.
4. A `slog.Handler` that reads the request's fields and the active span from the context of every `…Context` log call.

## Decision

Options 2 and 4, in `internal/platform/observability`.

- **Telemetry.**
  - The tracer and meter providers carry the resource `service.name` (`api-go` or `worker-go`), `service.version` and `deployment.environment` (`APP_ENV`).
  - Meters export to a dedicated Prometheus registry, served at `/metrics` on the admin listener (`ADMIN_ADDR`, default `:9090`), which also serves `/health/ready` and is never proxied. Runtime metrics come from `contrib/instrumentation/runtime`.
  - Spans are always created, so logs carry trace ids. They are exported over OTLP/HTTP to `{OTEL_EXPORTER_OTLP_ENDPOINT}/v1/traces` only when that variable is set. Without it no exporter goroutine runs (a `goleak` test checks this).
  - `App.Close` shuts the telemetry down, flushing spans then metrics, before it closes the pool.
- **HTTP.**
  - `otelhttp` is the outermost layer, accepting a W3C `traceparent`; the health probes are not traced.
  - After routing, the span is renamed to the mux pattern (`GET /api/v1/workouts/{workoutId}`) and gets `http.route`.
  - The access log records `http.server.request.duration` (seconds, buckets 5 ms to 10 s) and `http.server.requests`, labelled `method`, `route` (the pattern's path, `unmatched` when nothing matched) and `status`.
- **Database.** `otelpgx` gives a span per query and `db.client.operation.duration`, with statements but never arguments. At `LOG_LEVEL=debug`, `pgx/tracelog` writes one debug line per query, arguments left out.
- **Jobs.** A River worker middleware in `platform/jobs` records `jobs.active` and `jobs.failed`, both by `kind`. `jobs.failed` has a `reason` label: `discarded` when the last attempt failed, `cancelled` for `river.JobCancel`; a retried attempt is not a failed job. The series are exported at zero when the worker starts. They live on the worker's admin listener: the API process never works jobs.
- **Logs.**
  - `observability.LogHandler` adds the context's fields and `trace_id`/`span_id` to every record.
  - The request-id middleware puts `request_id` in the context; the auth policy adds `user_id` to the same shared field set, so the access log line written by an outer layer carries it too.
  - The business events keep PHP's names. `auth.login` and `user.registered` gain `client`.

## Consequences

- Good: one Grafana dashboard can chart both SLIs from the scraped metrics, using the queries in `docs/deployment.md`, and any log line of a request leads to its trace.
- Good: names and labels are ours and stable; a library upgrade cannot rename a dashboard's series.
- Bad: the HTTP metrics do not follow OpenTelemetry's semantic-convention label names (`route`, `status` instead of `http_route`, `http_response_status_code`).
- Bad: the access log keeps `route` as the full pattern with its method (`GET /api/v1/me`), as since story 3, while the metric label is the path alone.
- Follow-up: production scraping (Alloy's `prometheus.scrape` on `api-go:9090` and `worker-go:9090`) and an OTLP receiver come with the rollout. A request benchmark (`BenchmarkRequest` in `platform/httpapi`) can be run by hand; CI does not run it yet.

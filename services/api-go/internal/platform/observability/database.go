package observability

import (
	"context"
	"log/slog"

	"github.com/exaring/otelpgx"
	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/tracelog"
)

// QueryTracers returns the pgx tracers for the pool: otelpgx (a span per
// query and the db.client.operation.duration histogram, statements without
// their arguments) and, when logger logs at debug, one debug line per query.
func (t *Telemetry) QueryTracers(ctx context.Context, logger *slog.Logger) []pgx.QueryTracer {
	tracers := []pgx.QueryTracer{otelpgx.NewTracer(
		otelpgx.WithTracerProvider(t.tracerProvider),
		otelpgx.WithMeterProvider(t.meterProvider),
	)}

	if logger.Enabled(ctx, slog.LevelDebug) {
		tracers = append(tracers, &tracelog.TraceLog{Logger: queryLogger{logger: logger}, LogLevel: tracelog.LogLevelDebug})
	}

	return tracers
}

// queryLogger writes pgx's trace log to slog at debug. Query arguments are
// left out: they hold password hashes and tokens.
type queryLogger struct {
	logger *slog.Logger
}

func (q queryLogger) Log(ctx context.Context, _ tracelog.LogLevel, msg string, data map[string]any) {
	attrs := make([]slog.Attr, 0, len(data))
	for key, value := range data {
		if key != "args" {
			attrs = append(attrs, slog.Any(key, value))
		}
	}

	q.logger.LogAttrs(ctx, slog.LevelDebug, "pgx: "+msg, attrs...)
}

package httpapi

import (
	"context"
	"log/slog"
)

type ctxKey int

const (
	requestIDKey ctxKey = iota
	loggerKey
	routeKey
)

// RequestID returns the request id the RequestID middleware assigned.
func RequestID(ctx context.Context) string {
	id, _ := ctx.Value(requestIDKey).(string)

	return id
}

// Logger returns the request-scoped logger (with request_id), or the
// default logger outside a request.
func Logger(ctx context.Context) *slog.Logger {
	if logger, ok := ctx.Value(loggerKey).(*slog.Logger); ok {
		return logger
	}

	return slog.Default()
}

// route is filled in by the router once the mux has matched, so the access
// log, which runs outside the mux, can name the route.
type route struct{ pattern string }

func routeFrom(ctx context.Context) *route {
	r, _ := ctx.Value(routeKey).(*route)

	return r
}

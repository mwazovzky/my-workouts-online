package httpapi

import (
	"context"
	"log/slog"
	"net/http"
)

type ctxKey int

const (
	requestIDKey ctxKey = iota
	loggerKey
	routeKey
	requestKey
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

// route is filled in by inner layers so the access log, which runs outside
// the mux, can name the route and the user.
type route struct {
	pattern string
	userID  int64
}

func routeFrom(ctx context.Context) *route {
	r, _ := ctx.Value(routeKey).(*route)

	return r
}

// Request returns the HTTP request a contract operation is serving. The
// generated strict handlers receive only the declared parameters and body;
// adapters use this for what the contract leaves undeclared, such as the
// refresh cookie and the client IP.
func Request(ctx context.Context) *http.Request {
	r, _ := ctx.Value(requestKey).(*http.Request)

	return r
}

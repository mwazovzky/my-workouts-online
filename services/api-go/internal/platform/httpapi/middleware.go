package httpapi

import (
	"context"
	"errors"
	"log/slog"
	"net"
	"net/http"
	"runtime/debug"
	"strconv"
	"strings"
	"time"

	"github.com/google/uuid"
	"go.opentelemetry.io/otel/attribute"
	"go.opentelemetry.io/otel/metric"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/observability"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/ratelimit"
)

// RequestIDHeader carries the request id in and out.
const RequestIDHeader = "X-Request-Id"

// statusWriter records the status and size of a response. The outermost
// middleware installs it, so every inner layer can tell whether the header
// has been written.
type statusWriter struct {
	http.ResponseWriter
	status int
	bytes  int
}

func (sw *statusWriter) WriteHeader(status int) {
	if sw.status == 0 {
		sw.status = status
	}

	sw.ResponseWriter.WriteHeader(status)
}

func (sw *statusWriter) Write(b []byte) (int, error) {
	if sw.status == 0 {
		sw.status = http.StatusOK
	}

	n, err := sw.ResponseWriter.Write(b)
	sw.bytes += n

	return n, err
}

// Unwrap lets http.ResponseController reach the underlying writer.
func (sw *statusWriter) Unwrap() http.ResponseWriter { return sw.ResponseWriter }

// requestID takes a UUID from X-Request-Id or mints one, stores it in the
// context (and as the request_id log field) and echoes it on the response,
// error or not.
func requestID(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		id := r.Header.Get(RequestIDHeader)
		if _, err := uuid.Parse(id); err != nil || len(id) != len(uuid.Nil.String()) {
			id = uuid.NewString()
		}

		w.Header().Set(RequestIDHeader, id)

		ctx := observability.WithLogFields(context.WithValue(r.Context(), requestIDKey, id), slog.String("request_id", id))
		next.ServeHTTP(&statusWriter{ResponseWriter: w}, r.WithContext(ctx))
	})
}

// requestMetrics are the HTTP SLIs: latency and request count, by method,
// route (the mux pattern's path) and status.
type requestMetrics struct {
	duration metric.Float64Histogram
	requests metric.Int64Counter
}

// latencyBuckets are the duration histogram's bounds in seconds; the OTel
// defaults are meant for milliseconds.
var latencyBuckets = []float64{0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10}

func newRequestMetrics(meter metric.Meter) (requestMetrics, error) {
	duration, err := meter.Float64Histogram("http.server.request.duration",
		metric.WithUnit("s"), metric.WithDescription("Duration of HTTP requests."),
		metric.WithExplicitBucketBoundaries(latencyBuckets...))
	if err != nil {
		return requestMetrics{}, err
	}

	requests, err := meter.Int64Counter("http.server.requests", metric.WithDescription("HTTP requests served."))
	if err != nil {
		return requestMetrics{}, err
	}

	return requestMetrics{duration: duration, requests: requests}, nil
}

// unmatchedRoute labels requests no route matched, so stray paths cannot
// grow the metrics without bound.
const unmatchedRoute = "unmatched"

// routeLabel is the path part of a mux pattern ("GET /api/v1/x/{id}").
func routeLabel(pattern string) string {
	if pattern == "" {
		return unmatchedRoute
	}

	if _, path, ok := strings.Cut(pattern, " "); ok {
		return path
	}

	return pattern
}

// accessLog puts the logger in the context, logs one line per request when
// it completes and records the request metrics.
func accessLog(logger *slog.Logger, clk clock.Clock, metrics requestMetrics) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			start := clk.Now()
			matched := &route{}

			ctx := context.WithValue(r.Context(), loggerKey, logger)
			ctx = context.WithValue(ctx, routeKey, matched)

			next.ServeHTTP(w, r.WithContext(ctx))

			status, bytes := http.StatusOK, 0
			if sw, ok := w.(*statusWriter); ok && sw.status != 0 {
				status, bytes = sw.status, sw.bytes
			}

			elapsed := clk.Now().Sub(start)

			logger.LogAttrs(ctx, levelFor(status), "request",
				slog.String("method", r.Method),
				slog.String("path", r.URL.Path),
				slog.String("route", matched.pattern),
				slog.Int("status", status),
				slog.Int("bytes", bytes),
				slog.Float64("duration_ms", float64(elapsed.Microseconds())/1000),
			)

			labels := metric.WithAttributeSet(attribute.NewSet(
				attribute.String("method", r.Method),
				attribute.String("route", routeLabel(matched.pattern)),
				attribute.String("status", strconv.Itoa(status)),
			))
			metrics.duration.Record(ctx, elapsed.Seconds(), labels)
			metrics.requests.Add(ctx, 1, labels)
		})
	}
}

func levelFor(status int) slog.Level {
	switch {
	case status >= http.StatusInternalServerError:
		return slog.LevelError
	case status >= http.StatusBadRequest:
		return slog.LevelWarn
	default:
		return slog.LevelInfo
	}
}

// recoverPanics turns a panic into a 500 Problem and logs it with the stack.
// http.ErrAbortHandler is re-raised: it is how a handler aborts on purpose.
func recoverPanics(rs *Responder) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			defer func() {
				p := recover()
				if p == nil {
					return
				}

				if err, ok := p.(error); ok && errors.Is(err, http.ErrAbortHandler) {
					panic(p)
				}

				Logger(r.Context()).ErrorContext(r.Context(), "panic",
					"panic", p, "stack", string(debug.Stack()))

				// Too late for a Problem once the handler has started writing.
				if sw, ok := w.(*statusWriter); ok && sw.status != 0 {
					return
				}

				rs.Problem(w, r, http.StatusInternalServerError, apperr.CodeInternalError, "Server Error", nil)
			}()

			next.ServeHTTP(w, r)
		})
	}
}

// negotiateLocale picks the locale from Accept-Language, stores it in the
// context, and declares it: Content-Language, and Accept-Language in Vary
// so caches keep one copy per language.
func negotiateLocale(bundle *i18n.Bundle) func(http.Handler) http.Handler {
	supported := bundle.Locales()

	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			locale := i18n.Negotiate(r.Header.Get("Accept-Language"), supported, i18n.Source)

			w.Header().Set("Content-Language", string(locale))
			w.Header().Add("Vary", "Accept-Language")

			next.ServeHTTP(w, r.WithContext(i18n.WithLocale(r.Context(), locale)))
		})
	}
}

// rateLimit applies perMinute hits per client IP to /api/ paths, with
// X-RateLimit-Limit and X-RateLimit-Remaining on every response and a 429
// Problem with Retry-After over the limit. The client is the TCP peer: no
// X-Forwarded-For is trusted until a proxy fronts the service (story 13).
func rateLimit(limiter ratelimit.Limiter, perMinute int, rs *Responder) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if !strings.HasPrefix(r.URL.Path, "/api/") {
				next.ServeHTTP(w, r)

				return
			}

			decision, err := limiter.Allow(r.Context(), "api:"+ClientIP(r), perMinute, time.Minute)
			if err != nil {
				// A broken limiter must not take the API down.
				Logger(r.Context()).WarnContext(r.Context(), "rate limiter failed", "error", err.Error())
				next.ServeHTTP(w, r)

				return
			}

			w.Header().Set("X-RateLimit-Limit", strconv.Itoa(decision.Limit))
			w.Header().Set("X-RateLimit-Remaining", strconv.Itoa(decision.Remaining))

			if !decision.Allowed {
				rs.Error(w, r, apperr.RateLimited(apperr.CodeTooManyRequests, decision.RetryAfter))

				return
			}

			next.ServeHTTP(w, r)
		})
	}
}

// ClientIP is the request's client address: the TCP peer, since no proxy is
// trusted yet (story 13).
func ClientIP(r *http.Request) string {
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}

	return host
}

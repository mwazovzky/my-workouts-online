package observability

import (
	"context"
	"log/slog"
	"sync"

	"go.opentelemetry.io/otel/trace"
)

// LogHandler adds the context's log fields (request_id, user_id, …) and the
// active span's trace_id and span_id to every record logged with a context,
// so a use case's business event is tied to its request without passing a
// logger around.
type LogHandler struct {
	inner slog.Handler
}

// NewLogHandler wraps inner; wrapping a LogHandler again returns it as is.
func NewLogHandler(inner slog.Handler) slog.Handler {
	if h, ok := inner.(LogHandler); ok {
		return h
	}

	return LogHandler{inner: inner}
}

// Logger returns logger with its handler wrapped in a LogHandler.
func Logger(logger *slog.Logger) *slog.Logger {
	if _, ok := logger.Handler().(LogHandler); ok {
		return logger
	}

	return slog.New(NewLogHandler(logger.Handler()))
}

// Enabled implements slog.Handler.
func (h LogHandler) Enabled(ctx context.Context, level slog.Level) bool {
	return h.inner.Enabled(ctx, level)
}

// Handle implements slog.Handler.
func (h LogHandler) Handle(ctx context.Context, record slog.Record) error {
	attrs := fieldsOf(ctx)

	if span := trace.SpanContextFromContext(ctx); span.IsValid() {
		attrs = append(attrs, slog.String("trace_id", span.TraceID().String()), slog.String("span_id", span.SpanID().String()))
	}

	if len(attrs) > 0 {
		record = record.Clone()
		record.AddAttrs(attrs...)
	}

	return h.inner.Handle(ctx, record)
}

// WithAttrs implements slog.Handler.
func (h LogHandler) WithAttrs(attrs []slog.Attr) slog.Handler {
	return LogHandler{inner: h.inner.WithAttrs(attrs)}
}

// WithGroup implements slog.Handler.
func (h LogHandler) WithGroup(name string) slog.Handler {
	return LogHandler{inner: h.inner.WithGroup(name)}
}

type fieldsKey struct{}

// fields is a request's log fields. It is shared by pointer, so a field an
// inner layer adds (the user id, after authentication) reaches the outer
// layers' log lines too.
type fields struct {
	mu    sync.Mutex
	attrs []slog.Attr
}

// WithLogFields returns ctx carrying attrs as log fields. When ctx already
// carries fields, attrs are added to them, visible to every holder of the
// outer context.
func WithLogFields(ctx context.Context, attrs ...slog.Attr) context.Context {
	if f, ok := ctx.Value(fieldsKey{}).(*fields); ok {
		f.add(attrs)

		return ctx
	}

	return context.WithValue(ctx, fieldsKey{}, &fields{attrs: attrs})
}

// AddLogFields adds attrs to the fields ctx carries; without fields it does
// nothing.
func AddLogFields(ctx context.Context, attrs ...slog.Attr) {
	if f, ok := ctx.Value(fieldsKey{}).(*fields); ok {
		f.add(attrs)
	}
}

func (f *fields) add(attrs []slog.Attr) {
	f.mu.Lock()
	defer f.mu.Unlock()

	f.attrs = append(f.attrs, attrs...)
}

func fieldsOf(ctx context.Context) []slog.Attr {
	f, ok := ctx.Value(fieldsKey{}).(*fields)
	if !ok {
		return nil
	}

	f.mu.Lock()
	defer f.mu.Unlock()

	return append([]slog.Attr(nil), f.attrs...)
}

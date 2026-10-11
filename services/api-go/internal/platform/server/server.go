// Package server runs an http.Handler with graceful shutdown: when the
// context is cancelled it starts draining, waits, stops accepting connections
// and lets in-flight requests finish within a timeout.
package server

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"net"
	"net/http"
	"time"
)

// Fixed transport timeouts. ReadHeaderTimeout also guards against slowloris.
const (
	readHeaderTimeout = 5 * time.Second
	readTimeout       = 30 * time.Second
	writeTimeout      = 30 * time.Second
	idleTimeout       = 60 * time.Second
)

// Options configures the shutdown sequence.
type Options struct {
	// DrainDelay is how long the server keeps accepting requests after
	// OnDrain, so a proxy polling readiness can stop routing here.
	DrainDelay time.Duration
	// ShutdownTimeout bounds how long in-flight requests may take to finish.
	ShutdownTimeout time.Duration
	// OnDrain runs first when shutdown starts; the readiness probe flips to 503.
	OnDrain func()
	// Logger receives the lifecycle messages.
	Logger *slog.Logger
}

// Serve answers requests on ln until ctx is cancelled, then shuts down in
// order: OnDrain, DrainDelay, stop accepting, wait for in-flight requests.
// It returns nil after a clean shutdown.
func Serve(ctx context.Context, ln net.Listener, handler http.Handler, opts Options) error {
	srv := &http.Server{
		Handler:           handler,
		ReadHeaderTimeout: readHeaderTimeout,
		ReadTimeout:       readTimeout,
		WriteTimeout:      writeTimeout,
		IdleTimeout:       idleTimeout,
		ErrorLog:          slog.NewLogLogger(opts.Logger.Handler(), slog.LevelWarn),
	}

	serveErr := make(chan error, 1)
	go func() { serveErr <- srv.Serve(ln) }()

	select {
	case err := <-serveErr:
		return fmt.Errorf("serve: %w", err)
	case <-ctx.Done():
	}

	opts.Logger.InfoContext(ctx, "shutting down", "drain_delay", opts.DrainDelay.String(), "timeout", opts.ShutdownTimeout.String())

	if opts.OnDrain != nil {
		opts.OnDrain()
	}

	time.Sleep(opts.DrainDelay)

	shutdownCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), opts.ShutdownTimeout)
	defer cancel()

	if err := srv.Shutdown(shutdownCtx); err != nil {
		return fmt.Errorf("shutdown: %w", err)
	}

	if err := <-serveErr; !errors.Is(err, http.ErrServerClosed) {
		return fmt.Errorf("serve: %w", err)
	}

	opts.Logger.InfoContext(ctx, "shut down cleanly")

	return nil
}

package server_test

import (
	"context"
	"errors"
	"log/slog"
	"net"
	"net/http"
	"testing"
	"time"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/health"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/gen"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/server"
)

func TestServeDrainsInFlightRequestsAndReturnsNil(t *testing.T) {
	t.Parallel()

	started := make(chan struct{})
	release := make(chan struct{})
	handler := http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		close(started)
		<-release
		w.WriteHeader(http.StatusOK)
	})

	drained := make(chan struct{})
	ln, cancel, done := start(t, handler, server.Options{
		ShutdownTimeout: 5 * time.Second,
		OnDrain:         func() { close(drained) },
	})

	statuses := make(chan int, 1)
	go func() { statuses <- get(t, ln, "/slow") }()

	<-started
	cancel()
	<-drained
	close(release)

	if status := <-statuses; status != http.StatusOK {
		t.Errorf("in-flight request status = %d, want %d", status, http.StatusOK)
	}

	if err := <-done; err != nil {
		t.Errorf("Serve() error = %v, want nil", err)
	}
}

func TestServeAnswers503FromReadinessWhileDraining(t *testing.T) {
	t.Parallel()

	readiness := health.NewReadiness(clock.System{}, discardLogger())
	mux := http.NewServeMux()
	mux.HandleFunc("GET /health/ready", func(w http.ResponseWriter, r *http.Request) {
		response, err := readiness.GetReadiness(r.Context(), gen.GetReadinessRequestObject{})
		if err == nil {
			err = response.VisitGetReadinessResponse(w)
		}

		if err != nil {
			t.Errorf("readiness: %v", err)
		}
	})

	drained := make(chan struct{})
	ln, cancel, done := start(t, mux, server.Options{
		DrainDelay:      500 * time.Millisecond,
		ShutdownTimeout: 5 * time.Second,
		OnDrain: func() {
			readiness.StartDraining()
			close(drained)
		},
	})

	if status := get(t, ln, "/health/ready"); status != http.StatusOK {
		t.Fatalf("readiness before shutdown = %d, want %d", status, http.StatusOK)
	}

	cancel()
	<-drained

	if status := get(t, ln, "/health/ready"); status != http.StatusServiceUnavailable {
		t.Errorf("readiness while draining = %d, want %d", status, http.StatusServiceUnavailable)
	}

	if err := <-done; err != nil {
		t.Errorf("Serve() error = %v, want nil", err)
	}
}

func TestServeReportsAShutdownTimeout(t *testing.T) {
	t.Parallel()

	started := make(chan struct{})
	release := make(chan struct{})
	t.Cleanup(func() { close(release) })

	handler := http.HandlerFunc(func(_ http.ResponseWriter, _ *http.Request) {
		close(started)
		<-release
	})

	ln, cancel, done := start(t, handler, server.Options{ShutdownTimeout: 50 * time.Millisecond})

	go func() { get(t, ln, "/stuck") }()

	<-started
	cancel()

	if err := <-done; !errors.Is(err, context.DeadlineExceeded) {
		t.Errorf("Serve() error = %v, want context.DeadlineExceeded", err)
	}
}

func TestServeReturnsListenerErrors(t *testing.T) {
	t.Parallel()

	ln := listen(t)
	if err := ln.Close(); err != nil {
		t.Fatal(err)
	}

	err := server.Serve(t.Context(), ln, http.NotFoundHandler(), server.Options{Logger: discardLogger()})
	if !errors.Is(err, net.ErrClosed) {
		t.Errorf("Serve() error = %v, want net.ErrClosed", err)
	}
}

// start runs Serve in the background on a loopback listener and returns the
// listener, a func that cancels the serve context and a channel with Serve's result.
func start(t *testing.T, handler http.Handler, opts server.Options) (net.Listener, context.CancelFunc, <-chan error) {
	t.Helper()

	opts.Logger = discardLogger()
	ln := listen(t)
	ctx, cancel := context.WithCancel(t.Context())
	t.Cleanup(cancel)

	done := make(chan error, 1)
	go func() { done <- server.Serve(ctx, ln, handler, opts) }()

	return ln, cancel, done
}

func listen(t *testing.T) net.Listener {
	t.Helper()

	ln, err := (&net.ListenConfig{}).Listen(t.Context(), "tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatalf("listen: %v", err)
	}

	return ln
}

// get returns the response status, or 0 when the request fails.
func get(t *testing.T, ln net.Listener, path string) int {
	t.Helper()

	req, err := http.NewRequestWithContext(context.Background(), http.MethodGet, "http://"+ln.Addr().String()+path, http.NoBody)
	if err != nil {
		t.Errorf("new request: %v", err)

		return 0
	}

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return 0
	}

	if err := resp.Body.Close(); err != nil {
		t.Errorf("close body: %v", err)
	}

	return resp.StatusCode
}

func discardLogger() *slog.Logger {
	return slog.New(slog.DiscardHandler)
}

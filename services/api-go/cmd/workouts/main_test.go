package main

import (
	"bufio"
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestRunRejectsMissingAndUnknownCommands(t *testing.T) {
	t.Parallel()

	for _, args := range [][]string{nil, {"launch"}, {"serve", "-unknown-flag"}} {
		var stderr bytes.Buffer

		err := run(t.Context(), args, nil, &stderr)
		if !errors.Is(err, errUsage) {
			t.Errorf("run(%q) error = %v, want errUsage", args, err)
		}

		if !strings.Contains(stderr.String(), "usage: workouts") {
			t.Errorf("run(%q) printed %q, want the usage text", args, stderr.String())
		}
	}
}

func TestServeFailsWithoutRequiredConfig(t *testing.T) {
	t.Parallel()

	err := run(t.Context(), []string{"serve"}, map[string]string{}, io.Discard)
	if err == nil || !strings.Contains(err.Error(), "DATABASE_URL") {
		t.Errorf("run(serve) error = %v, want it to name DATABASE_URL", err)
	}
}

func TestServeAnswersHealthAndStopsCleanly(t *testing.T) {
	t.Parallel()

	logs, addrs := listeningAddr(t)
	ctx, cancel := context.WithCancel(t.Context())
	defer cancel()

	done := make(chan error, 1)
	go func() {
		done <- run(ctx, []string{"serve"}, map[string]string{
			"DATABASE_URL":         "postgres://unused",
			"HTTP_ADDR":            "127.0.0.1:0",
			"SHUTDOWN_DRAIN_DELAY": "0s",
		}, logs)
	}()

	var addr string
	select {
	case addr = <-addrs:
	case err := <-done:
		t.Fatalf("run(serve) returned early: %v", err)
	}

	if status := statusOf(t, "http://"+addr+"/health"); status != http.StatusOK {
		t.Errorf("GET /health = %d, want %d", status, http.StatusOK)
	}

	cancel()

	if err := <-done; err != nil {
		t.Errorf("run(serve) error = %v, want nil", err)
	}
}

func TestHealthcheck(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		status  int
		wantErr bool
	}{
		{"healthy", http.StatusOK, false},
		{"unhealthy", http.StatusServiceUnavailable, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				if r.URL.Path != "/health" {
					w.WriteHeader(http.StatusNotFound)

					return
				}

				w.WriteHeader(tt.status)
			}))
			t.Cleanup(srv.Close)

			err := run(t.Context(), []string{"healthcheck"}, environFor(t, srv.Listener.Addr()), io.Discard)
			if (err != nil) != tt.wantErr {
				t.Errorf("run(healthcheck) error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func TestHealthcheckFailsWhenNothingListens(t *testing.T) {
	t.Parallel()

	ln, err := (&net.ListenConfig{}).Listen(t.Context(), "tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}

	addr := ln.Addr()
	if err := ln.Close(); err != nil {
		t.Fatal(err)
	}

	if err := run(t.Context(), []string{"healthcheck"}, environFor(t, addr), io.Discard); err == nil {
		t.Error("run(healthcheck) error = nil, want a connection error")
	}
}

func environFor(t *testing.T, addr net.Addr) map[string]string {
	t.Helper()

	_, port, err := net.SplitHostPort(addr.String())
	if err != nil {
		t.Fatal(err)
	}

	return map[string]string{"DATABASE_URL": "postgres://unused", "HTTP_ADDR": ":" + port}
}

// listeningAddr returns a log writer and a channel that receives the address
// from the server's "listening" log line.
func listeningAddr(t *testing.T) (io.Writer, <-chan string) {
	t.Helper()

	reader, writer := io.Pipe()
	t.Cleanup(func() { _ = reader.Close() })

	addrs := make(chan string, 1)
	go func() {
		scanner := bufio.NewScanner(reader)
		for scanner.Scan() {
			var line struct {
				Msg  string `json:"msg"`
				Addr string `json:"addr"`
			}
			if json.Unmarshal(scanner.Bytes(), &line) == nil && line.Msg == "listening" {
				addrs <- line.Addr
			}
		}
	}()

	return writer, addrs
}

func statusOf(t *testing.T, url string) int {
	t.Helper()

	req, err := http.NewRequestWithContext(t.Context(), http.MethodGet, url, http.NoBody)
	if err != nil {
		t.Fatal(err)
	}

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatal(err)
	}

	if err := resp.Body.Close(); err != nil {
		t.Fatal(err)
	}

	return resp.StatusCode
}

// Command workouts is the Go API's single binary. Subcommands:
//
//	workouts serve        run the HTTP API until SIGINT or SIGTERM
//	workouts healthcheck  probe the local /health endpoint (the image's HEALTHCHECK)
//
// Configuration comes from the environment; see internal/platform/config.
package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"io"
	"log/slog"
	"net"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/caarlos0/env/v11"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/bootstrap"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/server"
)

// version is set at build time with -ldflags "-X main.version=…".
var version = "dev"

const usage = `usage: workouts <command>

commands:
  serve        run the HTTP API until SIGINT or SIGTERM
  healthcheck  exit 0 when the local /health endpoint answers 200
`

const healthcheckTimeout = 2 * time.Second

// errUsage marks a command-line mistake; main exits 2 for it.
var errUsage = errors.New("usage")

func main() {
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	// After the first signal, restore the default handling so a second one
	// kills the process instead of waiting for the drain.
	go func() {
		<-ctx.Done()
		stop()
	}()

	err := run(ctx, os.Args[1:], env.ToMap(os.Environ()), os.Stderr)
	stop()

	switch {
	case errors.Is(err, errUsage):
		os.Exit(2)
	case err != nil:
		fmt.Fprintln(os.Stderr, "workouts:", err)
		os.Exit(1)
	}
}

// run dispatches a subcommand. It is main without the process globals, so
// tests can call it.
func run(ctx context.Context, args []string, environ map[string]string, stderr io.Writer) error {
	if len(args) == 0 {
		fmt.Fprint(stderr, usage)

		return errUsage
	}

	command, rest := args[0], args[1:]

	flags := flag.NewFlagSet(command, flag.ContinueOnError)
	flags.SetOutput(stderr)
	flags.Usage = func() { fmt.Fprint(stderr, usage) }

	if err := flags.Parse(rest); err != nil {
		return errUsage
	}

	switch command {
	case "serve":
		return serve(ctx, environ, stderr)
	case "healthcheck":
		return healthcheck(ctx, environ)
	default:
		fmt.Fprintf(stderr, "unknown command %q\n\n%s", command, usage)

		return errUsage
	}
}

func serve(ctx context.Context, environ map[string]string, stderr io.Writer) error {
	cfg, err := config.Load(environ)
	if err != nil {
		return err
	}

	logger := slog.New(slog.NewJSONHandler(stderr, &slog.HandlerOptions{Level: cfg.LogLevel})).
		With("service", "api-go", "version", version)
	slog.SetDefault(logger)

	app := bootstrap.New(bootstrap.Deps{Logger: logger, Clock: clock.System{}})

	ln, err := (&net.ListenConfig{}).Listen(ctx, "tcp", cfg.HTTPAddr)
	if err != nil {
		return fmt.Errorf("listen: %w", err)
	}

	logger.InfoContext(ctx, "listening", "addr", ln.Addr().String())

	serveErr := server.Serve(ctx, ln, app.Handler, server.Options{
		DrainDelay:      cfg.ShutdownDrainDelay,
		ShutdownTimeout: cfg.ShutdownTimeout,
		OnDrain:         app.Readiness.StartDraining,
		Logger:          logger,
	})

	return errors.Join(serveErr, app.Close(context.WithoutCancel(ctx)))
}

// healthcheck probes /health on the configured port over loopback. The
// distroless image has no shell or curl, so the binary checks itself.
func healthcheck(ctx context.Context, environ map[string]string) error {
	cfg, err := config.Load(environ)
	if err != nil {
		return err
	}

	_, port, err := net.SplitHostPort(cfg.HTTPAddr)
	if err != nil {
		return fmt.Errorf("healthcheck: %w", err)
	}

	ctx, cancel := context.WithTimeout(ctx, healthcheckTimeout)
	defer cancel()

	url := "http://" + net.JoinHostPort("127.0.0.1", port) + "/health"

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, http.NoBody)
	if err != nil {
		return fmt.Errorf("healthcheck: %w", err)
	}

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return fmt.Errorf("healthcheck: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("healthcheck: %s answered %d", url, resp.StatusCode)
	}

	return nil
}

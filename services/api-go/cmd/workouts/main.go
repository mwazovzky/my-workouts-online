// Command workouts is the Go API's single binary. Subcommands:
//
//	workouts serve           run the HTTP API until SIGINT or SIGTERM
//	workouts migrate up      apply the pending database migrations
//	workouts migrate status  list every migration and whether it is applied
//	workouts healthcheck     probe the local /health endpoint (the image's HEALTHCHECK)
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
	"path/filepath"
	"syscall"
	"text/tabwriter"
	"time"

	"github.com/caarlos0/env/v11"
	"github.com/jackc/pgx/v5/stdlib"
	"github.com/pressly/goose/v3"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/bootstrap"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/server"
	"github.com/mwazovzky/my-workouts-online/services/api-go/migrations"
)

// version is set at build time with -ldflags "-X main.version=…".
var version = "dev"

const usage = `usage: workouts <command>

commands:
  serve           run the HTTP API until SIGINT or SIGTERM
  migrate up      apply the pending database migrations
  migrate status  list every migration and whether it is applied
  healthcheck     exit 0 when the local /health endpoint answers 200
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

	err := run(ctx, os.Args[1:], env.ToMap(os.Environ()), os.Stdout, os.Stderr)
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
func run(ctx context.Context, args []string, environ map[string]string, stdout, stderr io.Writer) error {
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
	case "migrate":
		return migrate(ctx, flags.Args(), environ, stdout, stderr)
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

	pool, err := postgres.Open(ctx, cfg.DatabaseURL)
	if err != nil {
		return err
	}

	app, err := bootstrap.New(bootstrap.Deps{Config: cfg, Logger: logger, Clock: clock.System{}, Pool: pool})
	if err != nil {
		pool.Close()

		return err
	}

	ln, err := (&net.ListenConfig{}).Listen(ctx, "tcp", cfg.HTTPAddr)
	if err != nil {
		return errors.Join(fmt.Errorf("listen: %w", err), app.Close(context.WithoutCancel(ctx)))
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

// migrate applies or lists the migrations in the migrations package. The
// deploy runs `migrate up` before starting the new version; running it again
// is a no-op.
func migrate(ctx context.Context, args []string, environ map[string]string, stdout, stderr io.Writer) error {
	if len(args) != 1 || (args[0] != "up" && args[0] != "status") {
		fmt.Fprint(stderr, usage)

		return errUsage
	}

	cfg, err := config.Load(environ)
	if err != nil {
		return err
	}

	pool, err := postgres.Open(ctx, cfg.DatabaseURL)
	if err != nil {
		return err
	}
	defer pool.Close()

	db := stdlib.OpenDBFromPool(pool)
	defer db.Close()

	migrator, err := postgres.NewMigrator(db, migrations.FS, migrations.Go()...)
	if err != nil {
		return err
	}

	if args[0] == "status" {
		statuses, err := migrator.Status(ctx)
		if err != nil {
			return fmt.Errorf("migrate status: %w", err)
		}

		printStatus(stdout, statuses)

		return nil
	}

	results, err := migrator.Up(ctx)
	if err != nil {
		return fmt.Errorf("migrate up: %w", err)
	}

	for _, result := range results {
		fmt.Fprintf(stdout, "applied %s in %s\n", sourceName(result.Source), result.Duration.Round(time.Millisecond))
	}

	if len(results) == 0 {
		fmt.Fprintln(stdout, "no pending migrations")
	}

	return nil
}

func printStatus(w io.Writer, statuses []*goose.MigrationStatus) {
	tw := tabwriter.NewWriter(w, 0, 0, 2, ' ', 0)
	fmt.Fprintln(tw, "MIGRATION\tSTATE\tAPPLIED AT")

	for _, status := range statuses {
		appliedAt := "-"
		if status.State == goose.StateApplied {
			appliedAt = status.AppliedAt.UTC().Format(time.RFC3339)
		}

		fmt.Fprintf(tw, "%s\t%s\t%s\n", sourceName(status.Source), status.State, appliedAt)
	}

	_ = tw.Flush()
}

// sourceName is the migration's file name, or its version for a Go migration
// registered without a file.
func sourceName(source *goose.Source) string {
	if source.Path == "" {
		return fmt.Sprintf("%05d (go)", source.Version)
	}

	return filepath.Base(source.Path)
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

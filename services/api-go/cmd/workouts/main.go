// Command workouts is the Go API's single binary. Subcommands:
//
//	workouts serve           run the HTTP API until SIGINT or SIGTERM
//	workouts work            run the background job workers until SIGINT or SIGTERM
//	workouts migrate up      apply the pending database migrations
//	workouts migrate status  list every migration and whether it is applied
//	workouts seed            create the verified demo admin (refused in production)
//	workouts user grant-admin <email>  make a verified user an admin
//	workouts keys generate   print a new JWT key pair for the environment
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
	"strings"
	"syscall"
	"text/tabwriter"
	"time"

	"github.com/caarlos0/env/v11"
	"github.com/jackc/pgx/v5/stdlib"
	"github.com/pressly/goose/v3"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/bootstrap"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app/catalogsync"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/jwt"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/jobs"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/observability"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/server"
	"github.com/mwazovzky/my-workouts-online/services/api-go/migrations"
)

// version is set at build time with -ldflags "-X main.version=…".
var version = "dev"

const usage = `usage: workouts <command>

commands:
  serve           run the HTTP API until SIGINT or SIGTERM
  work            run the background job workers (the support inbox forward)
                  until SIGINT or SIGTERM
  migrate up      apply the pending database migrations
  migrate status  list every migration and whether it is applied
  seed            create the verified demo admin test@example.com and apply the
                  catalog (not in production)
  catalog sync [--dry-run] [--strict]
                  apply the YAML catalog in CATALOG_DIR; --dry-run writes
                  nothing, --strict writes nothing when there are warnings
  user grant-admin <email>
                  give a registered, verified user the Admin role
  keys generate   print a new JWT_PRIVATE_KEY and JWT_PUBLIC_KEY
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
	case "work":
		return work(ctx, environ, stderr)
	case "migrate":
		return migrate(ctx, flags.Args(), environ, stdout, stderr)
	case "seed":
		return seed(ctx, environ, stdout, stderr)
	case "catalog":
		return catalog(ctx, flags.Args(), environ, stdout, stderr)
	case "user":
		return user(ctx, flags.Args(), environ, stdout, stderr)
	case "keys":
		return keys(flags.Args(), stdout, stderr)
	case "healthcheck":
		return healthcheck(ctx, environ)
	default:
		fmt.Fprintf(stderr, "unknown command %q\n\n%s", command, usage)

		return errUsage
	}
}

func serve(ctx context.Context, environ map[string]string, stderr io.Writer) error {
	proc, err := start(ctx, environ, stderr, "api-go", false)
	if err != nil {
		return err
	}

	ln, err := (&net.ListenConfig{}).Listen(ctx, "tcp", proc.cfg.HTTPAddr)
	if err != nil {
		return errors.Join(fmt.Errorf("listen: %w", err), proc.close(ctx))
	}

	proc.logger.InfoContext(ctx, "listening", "addr", ln.Addr().String())

	return proc.runWithAdmin(ctx, func() error {
		return server.Serve(ctx, ln, proc.app.Handler, server.Options{
			DrainDelay:      proc.cfg.ShutdownDrainDelay,
			ShutdownTimeout: proc.cfg.ShutdownTimeout,
			OnDrain:         proc.app.Readiness.StartDraining,
			Logger:          proc.logger,
		})
	})
}

// work runs the job workers until ctx is cancelled; jobs still running then
// get SHUTDOWN_TIMEOUT to finish before they are cancelled and retried later.
func work(ctx context.Context, environ map[string]string, stderr io.Writer) error {
	proc, err := start(ctx, environ, stderr, "worker-go", true)
	if err != nil {
		return err
	}

	return proc.runWithAdmin(ctx, func() error {
		return jobs.Run(ctx, proc.app.Jobs, proc.cfg.ShutdownTimeout, proc.logger)
	})
}

// process is what serve and work share: the configuration, the logger, the
// telemetry and the wired application.
type process struct {
	cfg       config.Config
	logger    *slog.Logger
	telemetry *observability.Telemetry
	app       *bootstrap.App
}

// start loads the configuration and builds the telemetry, the traced pool
// and the application, as service (the service.name of logs and traces).
func start(ctx context.Context, environ map[string]string, stderr io.Writer, service string, work bool) (*process, error) {
	cfg, err := config.Load(environ)
	if err != nil {
		return nil, err
	}

	logger := slog.New(observability.NewLogHandler(slog.NewJSONHandler(stderr, &slog.HandlerOptions{Level: cfg.LogLevel}))).
		With("service", service, "version", version)
	slog.SetDefault(logger)

	telemetry, err := observability.New(ctx, observability.Config{
		ServiceName: service, Version: version, Environment: cfg.AppEnv, OTLPEndpoint: cfg.OTLPEndpoint,
	})
	if err != nil {
		return nil, err
	}

	pool, err := postgres.Open(ctx, cfg.DatabaseURL, telemetry.QueryTracers(ctx, logger)...)
	if err != nil {
		return nil, errors.Join(err, telemetry.Close(context.WithoutCancel(ctx)))
	}

	app, err := bootstrap.New(bootstrap.Deps{
		Config: cfg, Logger: logger, Clock: clock.System{}, Pool: pool, Work: work, Telemetry: telemetry,
	})
	if err != nil {
		pool.Close()

		return nil, errors.Join(err, telemetry.Close(context.WithoutCancel(ctx)))
	}

	return &process{cfg: cfg, logger: logger, telemetry: telemetry, app: app}, nil
}

// runWithAdmin serves the admin listener (ADMIN_ADDR: /metrics and
// /health/ready, never proxied) while run runs, then closes the application:
// telemetry flushed, pool closed.
func (p *process) runWithAdmin(ctx context.Context, run func() error) error {
	ln, err := (&net.ListenConfig{}).Listen(ctx, "tcp", p.cfg.AdminAddr)
	if err != nil {
		return errors.Join(fmt.Errorf("admin listen: %w", err), p.close(ctx))
	}

	p.logger.InfoContext(ctx, "admin listening", "addr", ln.Addr().String())

	mux := http.NewServeMux()
	mux.Handle("GET /metrics", p.telemetry.MetricsHandler())
	mux.Handle("GET /health/ready", p.app.Readiness)

	adminErr := make(chan error, 1)

	go func() {
		adminErr <- server.Serve(ctx, ln, mux, server.Options{ShutdownTimeout: p.cfg.ShutdownTimeout, Logger: p.logger})
	}()

	runErr := run()

	return errors.Join(runErr, <-adminErr, p.close(ctx))
}

func (p *process) close(ctx context.Context) error {
	return p.app.Close(context.WithoutCancel(ctx))
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

// errProduction refuses demo seeding where real users live.
var errProduction = errors.New("seed creates a demo admin with a known password and must not run in production (APP_ENV=production)")

// seed creates the verified demo admin the contract tests sign in with and
// applies the catalog, as PHP's DatabaseSeeder runs CatalogSeeder.
func seed(ctx context.Context, environ map[string]string, stdout, stderr io.Writer) error {
	return withApp(ctx, environ, stderr, func(cfg config.Config, app *bootstrap.App) error {
		if cfg.IsProduction() {
			return errProduction
		}

		admin, err := app.Identity.SeedDemoAdmin(ctx)
		if err != nil {
			return fmt.Errorf("seed: %w", err)
		}

		fmt.Fprintf(stdout, "seeded the demo admin %s (verified)\n", admin.Email)

		return syncCatalog(ctx, app, catalogsync.Options{}, stdout)
	})
}

// errCatalog is a sync that wrote nothing: an invalid catalog, or strict
// mode with warnings. The details are already printed.
var errCatalog = errors.New("catalog sync failed")

// catalog runs `catalog sync [--dry-run] [--strict]`, as PHP's catalog:sync:
// exit 0 when synced or on a dry run, 1 when the catalog is invalid or
// strict mode refused the warnings.
func catalog(ctx context.Context, args []string, environ map[string]string, stdout, stderr io.Writer) error {
	if len(args) == 0 || args[0] != "sync" {
		fmt.Fprint(stderr, usage)

		return errUsage
	}

	flags := flag.NewFlagSet("catalog sync", flag.ContinueOnError)
	flags.SetOutput(stderr)
	flags.Usage = func() { fmt.Fprint(stderr, usage) }
	dryRun := flags.Bool("dry-run", false, "report what would change without writing anything")
	strict := flags.Bool("strict", false, "write nothing and fail when the sync raises warnings")

	if err := flags.Parse(args[1:]); err != nil || flags.NArg() > 0 {
		return errUsage
	}

	return withApp(ctx, environ, stderr, func(_ config.Config, app *bootstrap.App) error {
		return syncCatalog(ctx, app, catalogsync.Options{DryRun: *dryRun, Strict: *strict}, stdout)
	})
}

func syncCatalog(ctx context.Context, app *bootstrap.App, opts catalogsync.Options, stdout io.Writer) error {
	report, err := app.CatalogSync.Run(ctx, opts)

	var invalid *catalogsync.InvalidError
	if errors.As(err, &invalid) {
		fmt.Fprintln(stdout, "The catalog data is invalid. Nothing was written.")

		for _, message := range invalid.Messages() {
			fmt.Fprintf(stdout, "  - %s\n", message)
		}

		return errCatalog
	}

	if err != nil {
		return fmt.Errorf("catalog sync: %w", err)
	}

	printReport(report, stdout)

	switch {
	case report.DryRun:
		fmt.Fprintln(stdout, "Dry run: nothing was written.")
	case !report.Committed:
		fmt.Fprintln(stdout, "Strict mode: the sync raised warnings. Nothing was written.")

		return errCatalog
	default:
		fmt.Fprintln(stdout, "Catalog synced.")
	}

	return nil
}

// printReport writes the counts table, the changes and the warnings.
func printReport(report *catalogsync.Report, stdout io.Writer) {
	w := tabwriter.NewWriter(stdout, 0, 0, 2, ' ', 0)

	fmt.Fprint(w, "TYPE")
	for _, outcome := range catalogsync.Outcomes {
		fmt.Fprintf(w, "\t%s", strings.ToUpper(string(outcome)))
	}

	fmt.Fprintln(w)

	for _, kind := range ports.Kinds {
		fmt.Fprint(w, kind)
		for _, outcome := range catalogsync.Outcomes {
			fmt.Fprintf(w, "\t%d", report.Count(kind, outcome))
		}

		fmt.Fprintln(w)
	}

	_ = w.Flush()

	for _, change := range report.Changes {
		fmt.Fprintf(stdout, "  %s\n", change)
	}

	for _, warning := range report.Warnings {
		fmt.Fprintf(stdout, "  Warning: %s\n", warning)
	}
}

// user runs `user grant-admin <email>`, as the PHP user:grant-admin command.
func user(ctx context.Context, args []string, environ map[string]string, stdout, stderr io.Writer) error {
	if len(args) != 2 || args[0] != "grant-admin" {
		fmt.Fprint(stderr, usage)

		return errUsage
	}

	email := args[1]

	return withApp(ctx, environ, stderr, func(_ config.Config, app *bootstrap.App) error {
		already, err := app.Identity.GrantAdmin(ctx, email)
		if err != nil {
			return fmt.Errorf("%s: %w", email, err)
		}

		if already {
			fmt.Fprintf(stdout, "%s is already an admin.\n", email)
		} else {
			fmt.Fprintf(stdout, "%s is now an admin.\n", email)
		}

		return nil
	})
}

// keys runs `keys generate`: a new Ed25519 key pair in the format both
// backends read.
func keys(args []string, stdout, stderr io.Writer) error {
	if len(args) != 1 || args[0] != "generate" {
		fmt.Fprint(stderr, usage)

		return errUsage
	}

	private, public, err := jwt.GenerateKeys()
	if err != nil {
		return err
	}

	fmt.Fprintf(stdout, "Add these to your .env (never commit the private key):\n\nJWT_PRIVATE_KEY=%s\nJWT_PUBLIC_KEY=%s\n", private, public)

	return nil
}

// withApp builds the application for a command that needs the database and
// closes it afterwards.
func withApp(ctx context.Context, environ map[string]string, stderr io.Writer, fn func(config.Config, *bootstrap.App) error) error {
	cfg, err := config.Load(environ)
	if err != nil {
		return err
	}

	logger := slog.New(slog.NewJSONHandler(stderr, &slog.HandlerOptions{Level: cfg.LogLevel}))

	pool, err := postgres.Open(ctx, cfg.DatabaseURL)
	if err != nil {
		return err
	}

	app, err := bootstrap.New(bootstrap.Deps{Config: cfg, Logger: logger, Clock: clock.System{}, Pool: pool})
	if err != nil {
		pool.Close()

		return err
	}

	return errors.Join(fn(cfg, app), app.Close(context.WithoutCancel(ctx)))
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

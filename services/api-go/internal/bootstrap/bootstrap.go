// Package bootstrap is the composition root: the only package that imports
// every module and wires adapters into use cases. cmd/workouts and the HTTP
// tests build the application through New.
package bootstrap

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"maps"
	"net/http"
	"os"
	"slices"

	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/riverqueue/river"
	"go.opentelemetry.io/otel/metric"
	"go.opentelemetry.io/otel/trace"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/catalogfile"
	cataloghttp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/http"
	catalogpg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/adapters/postgres"
	catalogapp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app"
	catalogadmin "github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app/admin"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/catalog/app/catalogsync"
	dashboardhttp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/dashboard/adapters/http"
	dashboardapp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/dashboard/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/bcrypt"
	identityhttp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/http"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/jwt"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/links"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/notify"
	identitypg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/adapters/postgres"
	identityapp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/identity/app"
	inboxhttp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/adapters/http"
	inboxjobs "github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/adapters/jobs"
	inboxpg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/adapters/postgres"
	inboxresend "github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/adapters/resend"
	inboxapp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/i18n"
	kernelmail "github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/health"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/jobs"
	platformmail "github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/meta"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/observability"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/ratelimit"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/resend"
	traininghttp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/adapters/http"
	trainingpg "github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/adapters/postgres"
	trainingapp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/training/app"
)

// Deps are the process-level dependencies the application is built from.
type Deps struct {
	Config config.Config
	Logger *slog.Logger
	Clock  clock.Clock
	// Pool is the database. New takes ownership: App.Close closes it.
	Pool *pgxpool.Pool
	// Mailer sends email; nil selects it from MAIL_MAILER (log or resend).
	// Tests pass a mail.Fake to read what was sent.
	Mailer kernelmail.Mailer
	// Work builds a job client that works jobs (`workouts work`); without it
	// the client only inserts them.
	Work bool
	// Telemetry traces requests and jobs and receives the metrics; nil
	// records nothing. New takes ownership: App.Close flushes it before
	// closing the pool.
	Telemetry *observability.Telemetry
}

// App is the wired application.
type App struct {
	// Handler serves every route.
	Handler http.Handler
	// Readiness backs /health/ready; components register their checks on it
	// and the server flips it to draining on shutdown.
	Readiness *health.Readiness
	// Identity runs identity's use cases for the CLI (seed, user grant-admin).
	Identity *identityapp.Service
	// CatalogSync applies the YAML catalog in CATALOG_DIR (catalog sync, seed).
	CatalogSync *catalogsync.Syncer
	// Jobs is the River client; `workouts work` runs it with jobs.Run.
	Jobs *jobs.Client

	closers []func(context.Context) error
}

// New wires the application. It fails when an embedded asset (the contract,
// a message catalog) does not load or a configured key or URL is malformed.
func New(deps Deps) (*App, error) {
	cfg := deps.Config
	// Every log line made with a request's context carries its fields.
	deps.Logger = observability.Logger(deps.Logger)

	var (
		tracerProvider trace.TracerProvider
		meterProvider  metric.MeterProvider
	)

	if deps.Telemetry != nil {
		tracerProvider, meterProvider = deps.Telemetry.TracerProvider(), deps.Telemetry.MeterProvider()
	}

	bundle, err := i18n.NewBundle()
	if err != nil {
		return nil, err
	}

	keys, err := jwt.ParseKeys(cfg.JWT.PrivateKey, cfg.JWT.PublicKey, cfg.JWT.KeyID)
	if err != nil {
		return nil, fmt.Errorf("bootstrap: %w", err)
	}

	// One in-memory limiter, its keys namespaced per use: the global API
	// limit, per-operation throttles and the login lockout.
	limiter := ratelimit.NewMemory(deps.Clock)
	tx := postgres.NewTransactor(deps.Pool)
	signer := jwt.NewSigner(jwt.Config{
		Keys: keys, Issuer: cfg.JWT.Issuer, Audience: cfg.JWT.Audience, Leeway: cfg.JWT.Leeway(), Clock: deps.Clock,
	})

	resendClient, err := resend.New(resend.Config{
		BaseURL: cfg.Resend.BaseURL,
		APIKey:  cfg.Resend.Key,
		From:    kernelmail.Address{Name: cfg.Mail.FromName, Email: cfg.Mail.FromAddress},
	})
	if err != nil {
		return nil, err
	}

	mailer := deps.Mailer
	switch {
	case mailer != nil:
	case cfg.Mail.Mailer == config.MailerResend:
		mailer = resendClient
	default:
		mailer = platformmail.NewLog(deps.Logger)
	}

	notifier, err := notify.New(mailer, cfg.AppName)
	if err != nil {
		return nil, err
	}

	identity, err := identityapp.New(identityapp.Deps{
		Users:         identitypg.NewUsers(tx),
		RefreshTokens: identitypg.NewRefreshTokens(tx),
		Hasher:        bcrypt.New(cfg.BcryptRounds),
		Signer:        signer,
		LoginAttempts: limiter,
		Resets:        identitypg.NewPasswordResets(tx),
		Links:         links.New(cfg.FrontendURL, cfg.LinkSigningKey),
		Notifier:      notifier,
		Tx:            tx,
		Clock:         deps.Clock,
		Logger:        deps.Logger,
		AccessTTL:     cfg.JWT.AccessTTL(),
		RefreshTTL:    cfg.JWT.RefreshTTL(),
	})
	if err != nil {
		return nil, err
	}

	catalog := catalogapp.New(catalogapp.Deps{
		Programs:  catalogpg.NewPrograms(tx),
		Templates: catalogpg.NewTemplates(tx),
		Exercises: catalogpg.NewExercises(tx),
		Clock:     deps.Clock,
		Logger:    deps.Logger,
	})

	catalogStore := catalogpg.NewStore(tx)
	adminCatalog := catalogadmin.New(catalogadmin.Deps{
		Store: catalogStore, Lists: catalogpg.NewAdminLists(tx), Exercises: catalogpg.NewExercises(tx),
		Templates: catalogpg.NewTemplates(tx), Programs: catalogpg.NewPrograms(tx), Tx: tx, Clock: deps.Clock,
	})

	training := trainingapp.New(trainingapp.Deps{
		Workouts: trainingpg.NewWorkouts(tx),
		Catalog:  catalog,
		Tx:       tx,
		Clock:    deps.Clock,
		Logger:   deps.Logger,
	})

	dashboard := dashboardapp.New(dashboardapp.Deps{Catalog: catalog, Training: training, Clock: deps.Clock})

	// The inbox's worker forwards through the inbox service, which queues
	// through the client the worker is registered on; the closure breaks the
	// cycle and is only called once inbox is set. Retries are timed on the
	// wall clock, which River schedules by, even when deps.Clock is a fake.
	var inbox *inboxapp.Service

	workers := river.NewWorkers()
	river.AddWorker(workers, inboxjobs.NewForwardWorker(func(ctx context.Context, emailID string) error {
		return inbox.Forward(ctx, emailID)
	}, clock.System{}))

	jobClient, err := jobs.NewClient(deps.Pool, jobs.Options{
		Workers: workers, Logger: deps.Logger, Work: deps.Work,
		MeterProvider: meterProvider, Kinds: []string{inboxjobs.ForwardArgs{}.Kind()},
	})
	if err != nil {
		return nil, err
	}

	inbox = inboxapp.New(inboxapp.Deps{
		Source:    inboxresend.NewSource(resendClient),
		Receipts:  inboxpg.NewReceipts(tx),
		Queue:     inboxjobs.NewQueue(jobClient),
		Mailer:    mailer,
		Tx:        tx,
		Clock:     deps.Clock,
		Logger:    deps.Logger,
		ForwardTo: cfg.Mail.InboundForwardTo,
		AppName:   cfg.AppName,
	})

	locales := make([]string, 0, len(bundle.Locales()))
	for _, locale := range bundle.Locales() {
		locales = append(locales, string(locale))
	}

	catalogSync := catalogsync.New(catalogsync.Deps{
		Source:  catalogfile.New(os.DirFS(cfg.CatalogDir)),
		Store:   catalogStore,
		Tx:      tx,
		Clock:   deps.Clock,
		Locales: locales,
	})

	server, err := httpapi.NewServer(httpapi.Options{
		AppURL:             cfg.AppURL,
		RateLimitPerMinute: cfg.RateLimitPerMinute,
		Bundle:             bundle,
		Logger:             deps.Logger,
		Clock:              deps.Clock,
		Limiter:            limiter,
		Authenticator:      identity,
		Throttles:          identityhttp.Throttles(),
		FieldMessages:      fieldMessages(),
		TracerProvider:     tracerProvider,
		MeterProvider:      meterProvider,
	})
	if err != nil {
		return nil, err
	}

	readiness := health.NewReadiness(deps.Clock, deps.Logger)
	readiness.AddCheck("database", postgres.ReadinessCheck(deps.Pool))
	readiness.AddCheck("jwt", func(context.Context) error { return signer.Ready() })

	server.Mount(api{
		Liveness:  health.NewLiveness(deps.Clock),
		Readiness: readiness,
		Handler:   meta.NewHandler(bundle),
		Identity:  identityhttp.New(identity, signer, identityhttp.Config{CookieSecure: cfg.JWT.CookieSecure, Clock: deps.Clock}),
		Catalog:   cataloghttp.New(catalog, adminCatalog),
		Training:  traininghttp.New(training),
		Dashboard: dashboardhttp.New(dashboard),
	})
	server.Handle("GET /up", http.HandlerFunc(health.Up))

	webhook, err := inboxhttp.New(inbox, inboxhttp.Config{
		Secret: cfg.Resend.WebhookSecret, Clock: deps.Clock, WriteError: server.Responder().Error,
	})
	if err != nil {
		return nil, fmt.Errorf("bootstrap: %w", err)
	}

	server.Handle("POST "+inboxhttp.Path, webhook)

	// Closed in reverse: the telemetry is flushed while the pool is open.
	closers := []func(context.Context) error{func(context.Context) error { deps.Pool.Close(); return nil }}
	if deps.Telemetry != nil {
		closers = append(closers, deps.Telemetry.Close)
	}

	return &App{
		Handler:     server.Handler(),
		Readiness:   readiness,
		Identity:    identity,
		CatalogSync: catalogSync,
		Jobs:        jobClient,
		closers:     closers,
	}, nil
}

// fieldMessages merges the modules' validation wording.
func fieldMessages() map[string]httpapi.FieldMessages {
	out := traininghttp.FieldMessages()
	maps.Copy(out, cataloghttp.FieldMessages())

	return out
}

// Close releases resources in the reverse order of acquisition. It runs after
// the HTTP server has shut down, so no request can still be using them.
func (a *App) Close(ctx context.Context) error {
	var errs []error
	for _, closeFn := range slices.Backward(a.closers) {
		errs = append(errs, closeFn(ctx))
	}

	return errors.Join(errs...)
}

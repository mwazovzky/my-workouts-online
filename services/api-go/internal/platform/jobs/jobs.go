// Package jobs is the background job platform: a River client on the shared
// Postgres pool (River's tables come from migration 00004). The API process
// builds an insert-only client, so a use case can queue a job in its own
// transaction; `workouts work` builds a working one and runs it with Run.
// Modules define their job args and workers in an adapters/jobs package and
// register them on the Workers the composition root passes in.
package jobs

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"time"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/riverqueue/river"
	"github.com/riverqueue/river/riverdriver/riverpgxv5"
	"github.com/riverqueue/river/rivertype"
	"go.opentelemetry.io/otel/metric"
	"go.opentelemetry.io/otel/metric/noop"
)

// Client is River's client over pgx.
type Client = river.Client[pgx.Tx]

// maxWorkers is how many jobs the default queue works at once.
const maxWorkers = 10

// riverLogLevel is the least severe level River may log at: its debug lines
// (leader elections, producer status every few seconds) would flood a
// LOG_LEVEL=debug log.
const riverLogLevel = slog.LevelInfo

// Options configure a Client.
type Options struct {
	// Workers holds every job kind the process may insert or work.
	Workers *river.Workers
	// Logger receives River's messages from info up.
	Logger *slog.Logger
	// Work makes the client fetch and work jobs; without it the client only
	// inserts them.
	Work bool
	// MeterProvider receives jobs.active and jobs.failed from a working
	// client; nil records nothing.
	MeterProvider metric.MeterProvider
	// Kinds are the job kinds Workers holds; a working client exports their
	// metric series at zero before any job runs.
	Kinds []string
}

// NewClient returns a River client on pool. It starts nothing; a working
// client is started by Run.
func NewClient(pool *pgxpool.Pool, opts Options) (*Client, error) {
	logger := slog.New(atLeast{Handler: opts.Logger.Handler(), level: riverLogLevel})

	cfg := &river.Config{Workers: opts.Workers, Logger: logger}
	if opts.Work {
		cfg.Queues = map[string]river.QueueConfig{river.QueueDefault: {MaxWorkers: maxWorkers}}

		meterProvider := opts.MeterProvider
		if meterProvider == nil {
			meterProvider = noop.NewMeterProvider()
		}

		metrics, err := newMetricsMiddleware(meterProvider.Meter("github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/jobs"))
		if err != nil {
			return nil, fmt.Errorf("jobs: metrics: %w", err)
		}

		for _, kind := range opts.Kinds {
			metrics.register(context.Background(), kind)
		}

		cfg.Middleware = []rivertype.Middleware{metrics}
	}

	client, err := river.NewClient(riverpgxv5.New(pool), cfg)
	if err != nil {
		return nil, fmt.Errorf("jobs: %w", err)
	}

	return client, nil
}

// Run works jobs until ctx is cancelled, then stops: jobs still running get
// shutdownTimeout to finish, after which their contexts are cancelled and
// River retries them later. It returns nil after a clean stop.
func Run(ctx context.Context, client *Client, shutdownTimeout time.Duration, logger *slog.Logger) error {
	// Start's context only covers starting; stopping is driven below.
	if err := client.Start(context.WithoutCancel(ctx)); err != nil {
		return fmt.Errorf("jobs: start: %w", err)
	}

	logger.InfoContext(ctx, "working jobs")

	<-ctx.Done()

	logger.InfoContext(ctx, "stopping workers", "timeout", shutdownTimeout.String())

	stopCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), shutdownTimeout)
	defer cancel()

	err := client.Stop(stopCtx)
	if errors.Is(err, context.DeadlineExceeded) {
		logger.WarnContext(ctx, "workers did not finish in time; cancelling running jobs")

		// A fresh deadline: cancelled jobs return promptly.
		cancelCtx, cancelCancel := context.WithTimeout(context.WithoutCancel(ctx), shutdownTimeout)
		defer cancelCancel()

		err = client.StopAndCancel(cancelCtx)
	}

	if err != nil {
		return fmt.Errorf("jobs: stop: %w", err)
	}

	logger.InfoContext(ctx, "workers stopped")

	return nil
}

// atLeast drops records below level before the wrapped handler sees them.
type atLeast struct {
	slog.Handler

	level slog.Level
}

func (h atLeast) Enabled(ctx context.Context, level slog.Level) bool {
	return level >= h.level && h.Handler.Enabled(ctx, level)
}

func (h atLeast) WithAttrs(attrs []slog.Attr) slog.Handler {
	return atLeast{Handler: h.Handler.WithAttrs(attrs), level: h.level}
}

func (h atLeast) WithGroup(name string) slog.Handler {
	return atLeast{Handler: h.Handler.WithGroup(name), level: h.level}
}

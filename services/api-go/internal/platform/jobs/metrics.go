package jobs

import (
	"context"
	"errors"

	"github.com/riverqueue/river"
	"github.com/riverqueue/river/rivertype"
	"go.opentelemetry.io/otel/attribute"
	"go.opentelemetry.io/otel/metric"
)

// Reasons a job ends without succeeding, the jobs.failed reason label.
const (
	ReasonDiscarded = "discarded" // the last attempt failed
	ReasonCancelled = "cancelled" // the worker gave up (river.JobCancel)
)

// metricsMiddleware counts running jobs (jobs.active) and jobs that end
// without succeeding (jobs.failed), by kind. A failed attempt that River
// retries is not a failed job.
type metricsMiddleware struct {
	river.MiddlewareDefaults

	active metric.Int64UpDownCounter
	failed metric.Int64Counter
}

var _ rivertype.WorkerMiddleware = (*metricsMiddleware)(nil)

// NewMetricsMiddleware returns the worker middleware recording jobs.active
// and jobs.failed on meter. NewClient installs it; worker tests pass it to
// rivertest.
func NewMetricsMiddleware(meter metric.Meter) (rivertype.WorkerMiddleware, error) {
	return newMetricsMiddleware(meter)
}

func newMetricsMiddleware(meter metric.Meter) (*metricsMiddleware, error) {
	active, err := meter.Int64UpDownCounter("jobs.active", metric.WithDescription("Jobs being worked."))
	if err != nil {
		return nil, err
	}

	failed, err := meter.Int64Counter("jobs.failed", metric.WithDescription("Jobs that ended without succeeding."))
	if err != nil {
		return nil, err
	}

	return &metricsMiddleware{active: active, failed: failed}, nil
}

// register makes both series visible for kind before any job runs.
func (m *metricsMiddleware) register(ctx context.Context, kind string) {
	m.active.Add(ctx, 0, metric.WithAttributes(attribute.String("kind", kind)))

	for _, reason := range []string{ReasonDiscarded, ReasonCancelled} {
		m.failed.Add(ctx, 0, metric.WithAttributes(attribute.String("kind", kind), attribute.String("reason", reason)))
	}
}

// Work implements rivertype.WorkerMiddleware.
func (m *metricsMiddleware) Work(ctx context.Context, job *rivertype.JobRow, doInner func(context.Context) error) error {
	kind := attribute.String("kind", job.Kind)

	m.active.Add(ctx, 1, metric.WithAttributes(kind))
	err := doInner(ctx)
	m.active.Add(ctx, -1, metric.WithAttributes(kind))

	var cancel *rivertype.JobCancelError

	switch {
	case err == nil:
	case errors.As(err, &cancel):
		m.failed.Add(ctx, 1, metric.WithAttributes(kind, attribute.String("reason", ReasonCancelled)))
	case job.Attempt >= job.MaxAttempts:
		m.failed.Add(ctx, 1, metric.WithAttributes(kind, attribute.String("reason", ReasonDiscarded)))
	}

	return err
}

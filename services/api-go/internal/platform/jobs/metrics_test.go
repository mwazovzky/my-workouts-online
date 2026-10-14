package jobs_test

import (
	"context"
	"errors"
	"testing"

	"github.com/jackc/pgx/v5"
	"github.com/riverqueue/river"
	"github.com/riverqueue/river/riverdriver/riverpgxv5"
	"github.com/riverqueue/river/rivertest"
	"github.com/riverqueue/river/rivertype"
	"go.opentelemetry.io/otel/attribute"
	sdkmetric "go.opentelemetry.io/otel/sdk/metric"
	"go.opentelemetry.io/otel/sdk/metric/metricdata"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/jobs"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

type outcomeArgs struct {
	Outcome string `json:"outcome"`
}

func (outcomeArgs) Kind() string { return "test_outcome" }

// outcomeWorker succeeds, fails or cancels as its args say.
type outcomeWorker struct {
	river.WorkerDefaults[outcomeArgs]
}

func (outcomeWorker) Work(_ context.Context, job *river.Job[outcomeArgs]) error {
	switch job.Args.Outcome {
	case "fail":
		return errors.New("boom")
	case "cancel":
		return river.JobCancel(errors.New("gone"))
	default:
		return nil
	}
}

// counts returns the jobs.failed values by reason and the jobs.active sum.
func counts(t *testing.T, reader *sdkmetric.ManualReader) (map[string]int64, int64) {
	t.Helper()

	var rm metricdata.ResourceMetrics
	if err := reader.Collect(t.Context(), &rm); err != nil {
		t.Fatal(err)
	}

	failed, active := map[string]int64{}, int64(0)

	for _, scope := range rm.ScopeMetrics {
		for _, m := range scope.Metrics {
			sum, ok := m.Data.(metricdata.Sum[int64])
			if !ok {
				continue
			}

			for _, point := range sum.DataPoints {
				switch m.Name {
				case "jobs.failed":
					reason, _ := point.Attributes.Value(attribute.Key("reason"))
					failed[reason.AsString()] += point.Value
				case "jobs.active":
					active += point.Value
				}
			}
		}
	}

	return failed, active
}

func TestJobMetricsCountJobsThatEndWithoutSucceeding(t *testing.T) {
	t.Parallel()

	reader := sdkmetric.NewManualReader()
	middleware, err := jobs.NewMetricsMiddleware(sdkmetric.NewMeterProvider(sdkmetric.WithReader(reader)).Meter("test"))
	if err != nil {
		t.Fatal(err)
	}

	pool := testdb.New(t)
	worker := rivertest.NewWorker(t, riverpgxv5.New(pool), &river.Config{Middleware: []rivertype.Middleware{middleware}}, outcomeWorker{})

	tx, err := pool.Begin(t.Context())
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = tx.Rollback(context.Background()) })

	work := func(tx pgx.Tx, outcome string, maxAttempts int) {
		t.Helper()

		if _, err := worker.Work(t.Context(), t, tx, outcomeArgs{Outcome: outcome}, &river.InsertOpts{MaxAttempts: maxAttempts}); err != nil && outcome == "ok" {
			t.Fatal(err)
		}
	}

	work(tx, "ok", 5)
	work(tx, "fail", 5) // retried later: not a failed job
	work(tx, "fail", 1) // the last attempt: discarded
	work(tx, "cancel", 5)

	failed, active := counts(t, reader)
	if failed[jobs.ReasonDiscarded] != 1 || failed[jobs.ReasonCancelled] != 1 || active != 0 {
		t.Errorf("failed = %v, active = %d; want one discarded, one cancelled, none active", failed, active)
	}
}

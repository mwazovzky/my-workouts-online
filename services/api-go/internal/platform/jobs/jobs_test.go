package jobs_test

import (
	"context"
	"log/slog"
	"testing"
	"time"

	"github.com/riverqueue/river"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/jobs"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

type sleepArgs struct{}

func (sleepArgs) Kind() string { return "test_sleep" }

// sleepWorker signals when it starts, then waits for its context: a job
// that only stops when cancelled.
type sleepWorker struct {
	river.WorkerDefaults[sleepArgs]

	started chan struct{}
}

func (w *sleepWorker) Work(ctx context.Context, _ *river.Job[sleepArgs]) error {
	close(w.started)
	<-ctx.Done()

	return ctx.Err()
}

func TestRunCancelsAJobThatOutlivesTheShutdownTimeout(t *testing.T) {
	t.Parallel()

	pool := testdb.New(t)
	worker := &sleepWorker{started: make(chan struct{})}
	workers := river.NewWorkers()
	river.AddWorker(workers, worker)

	client, err := jobs.NewClient(pool, jobs.Options{Workers: workers, Logger: slog.New(slog.DiscardHandler), Work: true})
	if err != nil {
		t.Fatal(err)
	}

	ctx, stop := context.WithCancel(t.Context())
	done := make(chan error, 1)

	go func() { done <- jobs.Run(ctx, client, 200*time.Millisecond, slog.New(slog.DiscardHandler)) }()

	if _, err := client.Insert(t.Context(), sleepArgs{}, nil); err != nil {
		t.Fatal(err)
	}

	select {
	case <-worker.started:
	case <-time.After(30 * time.Second):
		t.Fatal("the job never started")
	}

	stopped := time.Now()

	stop()

	select {
	case err := <-done:
		if err != nil {
			t.Errorf("Run() = %v, want nil", err)
		}
	case <-time.After(10 * time.Second):
		t.Fatal("Run() did not return after the shutdown timeout")
	}

	if elapsed := time.Since(stopped); elapsed < 200*time.Millisecond {
		t.Errorf("Run() returned after %s, before the shutdown timeout", elapsed)
	}
}

func TestAnInsertOnlyClientCannotStart(t *testing.T) {
	t.Parallel()

	client, err := jobs.NewClient(testdb.New(t), jobs.Options{Workers: river.NewWorkers(), Logger: slog.New(slog.DiscardHandler)})
	if err != nil {
		t.Fatal(err)
	}

	if err := jobs.Run(t.Context(), client, time.Second, slog.New(slog.DiscardHandler)); err == nil {
		t.Error("Run() on an insert-only client = nil, want an error")
	}
}

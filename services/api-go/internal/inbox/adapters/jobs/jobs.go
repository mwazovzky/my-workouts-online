// Package jobs is the inbox's River job: forwarding a received email, with
// PHP's ForwardInboundEmail retry policy (5 attempts, backoff 60, 300 and
// 900 seconds), and the Queue that inserts it.
package jobs

import (
	"context"
	"errors"
	"fmt"
	"time"

	"github.com/riverqueue/river"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/jobs"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/postgres"
)

// MaxAttempts is how many times a forward is tried before River discards it.
const MaxAttempts = 5

// Backoff is the wait before each retry; the last applies to every later one.
var Backoff = []time.Duration{60 * time.Second, 300 * time.Second, 900 * time.Second}

// workTimeout bounds one attempt: one API call plus a 30-second download per
// attachment.
const workTimeout = 5 * time.Minute

// ForwardArgs is the job: forward the received email EmailID.
type ForwardArgs struct {
	EmailID string `json:"email_id"`
}

// Kind implements river.JobArgs.
func (ForwardArgs) Kind() string { return "forward_inbound_email" }

// InsertOpts implements river.JobArgsWithInsertOpts.
func (ForwardArgs) InsertOpts() river.InsertOpts {
	return river.InsertOpts{MaxAttempts: MaxAttempts}
}

// Forwarder runs the forward use case.
type Forwarder func(ctx context.Context, emailID string) error

// ForwardWorker works ForwardArgs jobs.
type ForwardWorker struct {
	river.WorkerDefaults[ForwardArgs]

	forward Forwarder
	clock   clock.Clock
}

// NewForwardWorker returns the worker. clock times the retries and must be
// the wall clock in production: River ignores a retry time before its own
// now.
func NewForwardWorker(forward Forwarder, clock clock.Clock) *ForwardWorker {
	return &ForwardWorker{forward: forward, clock: clock}
}

// Work implements river.Worker. An email the provider does not know cancels
// the job; any other error is retried.
func (w *ForwardWorker) Work(ctx context.Context, job *river.Job[ForwardArgs]) error {
	err := w.forward(ctx, job.Args.EmailID)
	if errors.Is(err, ports.ErrEmailNotFound) {
		return river.JobCancel(err)
	}

	return err
}

// NextRetry implements river.Worker: Backoff after the attempt that failed.
func (w *ForwardWorker) NextRetry(job *river.Job[ForwardArgs]) time.Time {
	return w.clock.Now().Add(Backoff[min(max(job.Attempt-1, 0), len(Backoff)-1)])
}

// Timeout implements river.Worker.
func (w *ForwardWorker) Timeout(*river.Job[ForwardArgs]) time.Duration {
	return workTimeout
}

// Queue implements ports.Queue with River.
type Queue struct {
	client *jobs.Client
}

var _ ports.Queue = Queue{}

// NewQueue returns a Queue inserting through client.
func NewQueue(client *jobs.Client) Queue {
	return Queue{client: client}
}

// EnqueueForward implements ports.Queue: inside a transaction the job is
// inserted in it.
func (q Queue) EnqueueForward(ctx context.Context, emailID string) error {
	var err error

	if tx, ok := postgres.TxFrom(ctx); ok {
		_, err = q.client.InsertTx(ctx, tx, ForwardArgs{EmailID: emailID}, nil)
	} else {
		_, err = q.client.Insert(ctx, ForwardArgs{EmailID: emailID}, nil)
	}

	if err != nil {
		return fmt.Errorf("insert forward job: %w", err)
	}

	return nil
}

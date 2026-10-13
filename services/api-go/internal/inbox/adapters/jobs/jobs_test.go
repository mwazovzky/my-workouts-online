package jobs_test

import (
	"bytes"
	"context"
	"fmt"
	"log/slog"
	"net/http"
	"strings"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"github.com/jackc/pgx/v5"
	"github.com/riverqueue/river"
	"github.com/riverqueue/river/riverdriver/riverpgxv5"
	"github.com/riverqueue/river/rivertest"
	"github.com/riverqueue/river/rivertype"

	inboxjobs "github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/adapters/jobs"
	inboxresend "github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/adapters/resend"
	inboxapp "github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/app"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/inbox/ports"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	kernelmail "github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/resend"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/resend/resendtest"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

type fixture struct {
	worker *rivertest.Worker[inboxjobs.ForwardArgs, pgx.Tx]
	tx     pgx.Tx
	clock  *clock.Fake
	mail   *mail.Fake
	resend *resendtest.Server
	logs   *bytes.Buffer
}

// newFixture works ForwardArgs jobs inside a transaction rolled back after
// the test; forwardTo is MAIL_INBOUND_FORWARD_TO.
func newFixture(t *testing.T, forwardTo string) *fixture {
	t.Helper()

	pool := testdb.New(t)
	f := &fixture{
		clock:  clock.NewFake(time.Date(2026, 10, 5, 20, 0, 0, 0, time.UTC)),
		mail:   &mail.Fake{},
		resend: resendtest.New(t),
		logs:   &bytes.Buffer{},
	}

	client, err := resend.New(resend.Config{BaseURL: f.resend.URL, APIKey: resendtest.APIKey})
	if err != nil {
		t.Fatal(err)
	}

	inbox := inboxapp.New(inboxapp.Deps{
		Source:    inboxresend.NewSource(client),
		Mailer:    f.mail,
		Clock:     f.clock,
		Logger:    slog.New(slog.NewJSONHandler(f.logs, nil)),
		ForwardTo: forwardTo,
		AppName:   "My Workouts",
	})

	// Retries are timed on the wall clock, as in production: River rejects a
	// retry time before its own now.
	f.worker = rivertest.NewWorker(t, riverpgxv5.New(pool), &river.Config{}, inboxjobs.NewForwardWorker(inbox.Forward, clock.System{}))

	f.tx, err = pool.Begin(t.Context())
	if err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { _ = f.tx.Rollback(context.Background()) })

	return f
}

// work runs one attempt; the error is the worker's, nil when it succeeded.
func (f *fixture) work(t *testing.T, emailID string) (*rivertest.WorkResult, error) {
	t.Helper()

	result, err := f.worker.Work(t.Context(), t, f.tx, inboxjobs.ForwardArgs{EmailID: emailID}, nil)
	if result == nil {
		t.Fatalf("the job was not worked: %v", err)
	}

	return result, err
}

func TestTheWorkerForwardsAnEmailWithItsAttachments(t *testing.T) {
	t.Parallel()

	f := newFixture(t, "owner@example.com")
	f.resend.AddEmail(resend.ReceivedEmail{
		ID: "email-1", From: "Jane Doe <jane@example.com>", To: []string{"support@my-workouts.online"},
		Subject: "Lost workout", HTML: "<p>My workout is gone</p>", CreatedAt: "2026-10-05 20:00:00",
	},
		resendtest.File{Filename: "screenshot.png", ContentType: "image/png", Content: []byte("png-bytes")},
		resendtest.File{Filename: "notes.txt", ContentType: "text/plain", Content: []byte("notes")},
	)

	result, err := f.work(t, "email-1")
	if err != nil || result.EventKind != river.EventKindJobCompleted {
		t.Fatalf("event = %s, want completed; errors %+v", result.EventKind, result.Job.Errors)
	}

	sent := f.mail.Sent("owner@example.com")
	if len(sent) != 1 {
		t.Fatalf("sent %d messages, want 1", len(sent))
	}

	msg := sent[0]
	if msg.FromName != "Jane Doe via My Workouts" || msg.Subject != "Lost workout" {
		t.Errorf("FromName, Subject = %q, %q", msg.FromName, msg.Subject)
	}

	if diff := cmp.Diff([]kernelmail.Address{{Name: "Jane Doe", Email: "jane@example.com"}}, msg.ReplyTo); diff != "" {
		t.Errorf("ReplyTo (-want +got):\n%s", diff)
	}

	for _, want := range []string{"<strong>From:</strong> Jane Doe &lt;jane@example.com&gt;", "<strong>Date:</strong> 2026-10-05 20:00:00", "<p>My workout is gone</p>"} {
		if !strings.Contains(msg.HTML, want) {
			t.Errorf("HTML lacks %q", want)
		}
	}

	want := []kernelmail.Attachment{
		{Filename: "screenshot.png", ContentType: "image/png", Content: []byte("png-bytes")},
		{Filename: "notes.txt", ContentType: "text/plain", Content: []byte("notes")},
	}
	if diff := cmp.Diff(want, msg.Attachments); diff != "" {
		t.Errorf("Attachments (-want +got):\n%s", diff)
	}
}

func TestAnEmailWithoutAttachmentsSkipsTheAttachmentCalls(t *testing.T) {
	t.Parallel()

	f := newFixture(t, "owner@example.com")
	f.resend.AddEmail(resend.ReceivedEmail{ID: "email-2", From: "jane@example.com", Text: "Hi"})

	if result, err := f.work(t, "email-2"); err != nil || result.EventKind != river.EventKindJobCompleted {
		t.Fatalf("event = %s, err = %v", result.EventKind, err)
	}

	if sent := f.mail.Sent("owner@example.com"); len(sent) != 1 || sent[0].Subject != "(no subject)" || len(sent[0].Attachments) != 0 {
		t.Errorf("sent %+v", sent)
	}

	if downloads := f.resend.DownloadAuthorization(); len(downloads) != 0 {
		t.Errorf("downloaded %d attachments, want none", len(downloads))
	}
}

func TestWithoutAForwardingAddressTheJobCompletesUnsent(t *testing.T) {
	t.Parallel()

	f := newFixture(t, "")

	if result, err := f.work(t, "email-1"); err != nil || result.EventKind != river.EventKindJobCompleted {
		t.Fatalf("event = %s, err = %v; want completed", result.EventKind, err)
	}

	if sent := f.mail.Sent("owner@example.com"); len(sent) != 0 {
		t.Errorf("sent %+v", sent)
	}

	if !strings.Contains(f.logs.String(), `"level":"WARN","msg":"Inbound email not forwarded: MAIL_INBOUND_FORWARD_TO is not set.","email_id":"email-1"`) {
		t.Errorf("logs lack the warning:\n%s", f.logs)
	}
}

func TestAnOutageIsRetriedAfterTheFirstBackoff(t *testing.T) {
	t.Parallel()

	f := newFixture(t, "owner@example.com")
	f.resend.FailWith(http.StatusInternalServerError)

	before := time.Now()
	result, err := f.work(t, "email-1")
	after := time.Now()

	if !strings.Contains(fmt.Sprint(err), "500") {
		t.Errorf("err = %v, want the 500", err)
	}

	if result.EventKind != river.EventKindJobFailed || result.Job.State != rivertype.JobStateRetryable {
		t.Fatalf("event, state = %s, %s; want failed, retryable", result.EventKind, result.Job.State)
	}

	if at := result.Job.ScheduledAt; at.Before(before.Add(time.Minute)) || at.After(after.Add(time.Minute)) {
		t.Errorf("scheduled at %s, want a minute after the attempt (%s to %s)", at, before, after)
	}

	if result.Job.MaxAttempts != inboxjobs.MaxAttempts || len(result.Job.Errors) != 1 {
		t.Errorf("max attempts %d, errors %+v", result.Job.MaxAttempts, result.Job.Errors)
	}
}

func TestAnEmailResendDoesNotKnowCancelsTheJob(t *testing.T) {
	t.Parallel()

	f := newFixture(t, "owner@example.com")

	result, _ := f.work(t, "missing")

	if result.EventKind != river.EventKindJobCancelled || result.Job.State != rivertype.JobStateCancelled {
		t.Errorf("event, state = %s, %s; want cancelled", result.EventKind, result.Job.State)
	}

	if errs := result.Job.Errors; len(errs) != 1 || !strings.Contains(errs[0].Error, ports.ErrEmailNotFound.Error()) {
		t.Errorf("job errors = %+v, want the not-found reason", errs)
	}
}

func TestBackoffFollowsThePHPJob(t *testing.T) {
	t.Parallel()

	now := time.Date(2026, 10, 5, 20, 0, 0, 0, time.UTC)
	worker := inboxjobs.NewForwardWorker(nil, clock.NewFake(now))

	for attempt, want := range map[int]time.Duration{1: time.Minute, 2: 5 * time.Minute, 3: 15 * time.Minute, 4: 15 * time.Minute} {
		job := &river.Job[inboxjobs.ForwardArgs]{JobRow: &rivertype.JobRow{Attempt: attempt}}
		if got := worker.NextRetry(job).Sub(now); got != want {
			t.Errorf("after attempt %d: retry in %s, want %s", attempt, got, want)
		}
	}

	if got := worker.Timeout(nil); got != 5*time.Minute {
		t.Errorf("Timeout() = %s", got)
	}
}

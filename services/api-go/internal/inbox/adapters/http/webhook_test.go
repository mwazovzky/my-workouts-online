package http_test

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"log/slog"
	"net/http"
	"testing"
	"time"

	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/riverqueue/river"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/bootstrap"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/apperr"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/clock"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/config"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/httpapi/apitest"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/jobs"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/mail"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/resend"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/resend/resendtest"
	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/platform/testdb"
)

func TestMain(m *testing.M) { testdb.Main(m) }

// The secret of PHP's ResendWebhookTest and its decoded key.
const (
	secret = "whsec_dGVzdC13ZWJob29rLXNlY3JldC0xMjM0NTY3OA=="
	key    = "test-webhook-secret-12345678"
)

type fixture struct {
	api    *apitest.Client
	app    *bootstrap.App
	pool   *pgxpool.Pool
	clock  *clock.Fake
	mail   *mail.Fake
	resend *resendtest.Server
}

type options struct {
	secret string
	work   bool
}

func newFixture(t *testing.T, opts options) *fixture {
	t.Helper()

	f := &fixture{
		pool:   testdb.New(t),
		clock:  clock.NewFake(time.Now().UTC().Truncate(time.Second)),
		mail:   &mail.Fake{},
		resend: resendtest.New(t),
	}

	app, err := bootstrap.New(bootstrap.Deps{
		Config: config.Config{
			AppURL: "http://localhost:8080", RateLimitPerMinute: 100_000, AppEnv: "testing", BcryptRounds: 4,
			AppName: "My Workouts", FrontendURL: "https://app.example.test", LinkSigningKey: "test-link-signing-key",
			Mail:   config.Mail{InboundForwardTo: "owner@example.com", FromAddress: "noreply@my-workouts.online"},
			Resend: config.Resend{Key: resendtest.APIKey, WebhookSecret: opts.secret, BaseURL: f.resend.URL},
		},
		Logger: slog.New(slog.DiscardHandler),
		Clock:  f.clock,
		Pool:   f.pool,
		Mailer: f.mail,
		Work:   opts.work,
	})
	if err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { _ = app.Close(context.Background()) })

	f.app, f.api = app, apitest.New(t, app.Handler)

	return f
}

func received(emailID string) map[string]any {
	return map[string]any{
		"type":       "email.received",
		"created_at": "2026-10-05T20:00:00.000Z",
		"data": map[string]any{
			"email_id": emailID, "from": "Jane <jane@example.com>", "to": []string{"support@my-workouts.online"},
			"subject": "Help", "attachments": []any{},
		},
	}
}

// post sends payload signed at the fixture's time.
func (f *fixture) post(t *testing.T, payload any) *apitest.Response {
	t.Helper()

	return f.postAt(t, payload, f.clock.Now().Unix())
}

func (f *fixture) postAt(t *testing.T, payload any, timestamp int64) *apitest.Response {
	t.Helper()

	body, err := json.Marshal(payload)
	if err != nil {
		t.Fatal(err)
	}

	mac := hmac.New(sha256.New, []byte(key))
	_, _ = fmt.Fprintf(mac, "msg_1.%d.%s", timestamp, body)

	return f.api.Do(apitest.Request{
		Method: http.MethodPost, Path: "/api/webhooks/resend", Body: string(body),
		Header: http.Header{
			"Content-Type":   {"application/json"},
			"Svix-Id":        {"msg_1"},
			"Svix-Timestamp": {fmt.Sprint(timestamp)},
			"Svix-Signature": {"v1," + base64.StdEncoding.EncodeToString(mac.Sum(nil))},
		},
	})
}

type state struct {
	jobs, receipts int
	emailIDs       []string
}

func (f *fixture) state(t *testing.T) state {
	t.Helper()

	var s state
	if err := f.pool.QueryRow(t.Context(),
		`SELECT count(*), coalesce(array_agg(args->>'email_id' ORDER BY id), '{}')
		 FROM river_job WHERE kind = 'forward_inbound_email' AND max_attempts = 5`,
	).Scan(&s.jobs, &s.emailIDs); err != nil {
		t.Fatal(err)
	}

	if err := f.pool.QueryRow(t.Context(), "SELECT count(*) FROM inbound_emails").Scan(&s.receipts); err != nil {
		t.Fatal(err)
	}

	return s
}

func TestAReceivedEmailIsQueuedOnce(t *testing.T) {
	t.Parallel()

	f := newFixture(t, options{secret: secret})

	f.post(t, received("email-123")).AssertStatus(http.StatusNoContent)

	if s := f.state(t); s.jobs != 1 || s.receipts != 1 || s.emailIDs[0] != "email-123" {
		t.Fatalf("after one delivery: %+v, want one job for email-123 and one receipt", s)
	}

	// Resend redelivers; the second delivery queues nothing.
	f.post(t, received("email-123")).AssertStatus(http.StatusNoContent)

	if s := f.state(t); s.jobs != 1 || s.receipts != 1 {
		t.Errorf("after a redelivery: %+v, want still one job and one receipt", s)
	}

	f.post(t, received("email-456")).AssertStatus(http.StatusNoContent)

	if s := f.state(t); s.jobs != 2 || s.receipts != 2 {
		t.Errorf("after another email: %+v, want two jobs", s)
	}
}

func TestReceiptsAreForgottenAfterAWeek(t *testing.T) {
	t.Parallel()

	f := newFixture(t, options{secret: secret})

	f.post(t, received("email-123")).AssertStatus(http.StatusNoContent)
	f.clock.Advance(7*24*time.Hour + time.Second)
	f.post(t, received("email-456")).AssertStatus(http.StatusNoContent)

	var ids []string
	if err := f.pool.QueryRow(t.Context(), "SELECT array_agg(email_id) FROM inbound_emails").Scan(&ids); err != nil {
		t.Fatal(err)
	}

	if len(ids) != 1 || ids[0] != "email-456" {
		t.Errorf("receipts = %v, want only email-456", ids)
	}
}

func TestOtherEventsAreAcknowledgedWithoutQueueing(t *testing.T) {
	t.Parallel()

	f := newFixture(t, options{secret: secret})

	for name, payload := range map[string]any{
		"another event type":       map[string]any{"type": "email.delivered", "data": map[string]any{"email_id": "email-123"}},
		"a received event, no id":  map[string]any{"type": "email.received", "data": map[string]any{}},
		"an id that is not a text": map[string]any{"type": "email.received", "data": map[string]any{"email_id": 42}},
		"not an object":            []any{"email.received"},
	} {
		f.post(t, payload).AssertStatus(http.StatusNoContent)

		if s := f.state(t); s.jobs != 0 || s.receipts != 0 {
			t.Errorf("%s: %+v, want nothing queued", name, s)
		}
	}
}

func TestUnsignedOrBadlySignedRequestsAreForbidden(t *testing.T) {
	t.Parallel()

	f := newFixture(t, options{secret: secret})

	tests := []struct {
		name       string
		res        func() *apitest.Response
		wantDetail string
	}{
		{"unsigned", func() *apitest.Response {
			return f.api.Do(apitest.Request{Method: http.MethodPost, Path: "/api/webhooks/resend", Body: received("email-123")})
		}, "Missing required headers"},
		{"forged", func() *apitest.Response {
			return f.api.Do(apitest.Request{
				Method: http.MethodPost, Path: "/api/webhooks/resend", Body: received("email-123"),
				Header: http.Header{
					"Svix-Id":        {"msg_1"},
					"Svix-Timestamp": {fmt.Sprint(f.clock.Now().Unix())},
					"Svix-Signature": {"v1," + base64.StdEncoding.EncodeToString([]byte("forged"))},
				},
			})
		}, "No signatures found matching the expected signature"},
		{"stale", func() *apitest.Response {
			return f.postAt(t, received("email-123"), f.clock.Now().Unix()-3600)
		}, "Message timestamp too old"},
	}

	for _, tt := range tests {
		problem := tt.res().AssertStatus(http.StatusForbidden).
			AssertHeader("Content-Type", "application/problem+json").Problem()

		if string(problem.Code) != string(apperr.CodeForbidden) || problem.Detail != tt.wantDetail ||
			problem.Type != "http://localhost:8080/problems/forbidden" ||
			*problem.Instance != "/api/webhooks/resend#"+apitest.RequestID {
			t.Errorf("%s: problem = %+v, want forbidden with %q", tt.name, problem, tt.wantDetail)
		}
	}

	if s := f.state(t); s.jobs != 0 || s.receipts != 0 {
		t.Errorf("rejected requests queued %+v", s)
	}
}

func TestEveryRequestIsForbiddenWithoutASecret(t *testing.T) {
	t.Parallel()

	f := newFixture(t, options{})

	problem := f.post(t, received("email-123")).AssertStatus(http.StatusForbidden).Problem()
	if problem.Detail != "Webhook secret is not configured." || string(problem.Code) != "forbidden" {
		t.Errorf("problem = %+v", problem)
	}

	if s := f.state(t); s.jobs != 0 {
		t.Errorf("queued %+v without a secret", s)
	}
}

func TestOnlyPostIsRouted(t *testing.T) {
	t.Parallel()

	f := newFixture(t, options{secret: secret})

	f.api.Do(apitest.Request{Method: http.MethodGet, Path: "/api/webhooks/resend"}).
		AssertStatus(http.StatusMethodNotAllowed).AssertHeader("Allow", "POST")
}

// TestAReceivedEmailIsForwardedByTheWorker runs the whole path: the webhook
// queues the job, a working client picks it up, the worker reads the email
// from the fake Resend API and the mailer sends it on.
func TestAReceivedEmailIsForwardedByTheWorker(t *testing.T) {
	t.Parallel()

	f := newFixture(t, options{secret: secret, work: true})
	f.resend.AddEmail(resend.ReceivedEmail{
		ID: "email-123", From: "Jane Doe <jane@example.com>", To: []string{"support@my-workouts.online"},
		Subject: "Lost workout", HTML: "<p>My workout is gone</p>", CreatedAt: "2026-10-05 20:00:00",
	}, resendtest.File{Filename: "screenshot.png", ContentType: "image/png", Content: []byte("png-bytes")})

	completed, cancelSubscription := f.app.Jobs.Subscribe(river.EventKindJobCompleted, river.EventKindJobFailed)
	defer cancelSubscription()

	ctx, stop := context.WithCancel(t.Context())
	done := make(chan error, 1)

	go func() { done <- jobs.Run(ctx, f.app.Jobs, 5*time.Second, slog.New(slog.DiscardHandler)) }()

	f.post(t, received("email-123")).AssertStatus(http.StatusNoContent)

	select {
	case event := <-completed:
		if event.Kind != river.EventKindJobCompleted {
			t.Fatalf("job %s: %+v", event.Kind, event.Job.Errors)
		}
	case <-time.After(30 * time.Second):
		t.Fatal("the forward job did not complete")
	}

	stop()

	if err := <-done; err != nil {
		t.Errorf("jobs.Run() = %v", err)
	}

	sent := f.mail.Sent("owner@example.com")
	if len(sent) != 1 || sent[0].FromName != "Jane Doe via My Workouts" || sent[0].Subject != "Lost workout" ||
		len(sent[0].Attachments) != 1 || string(sent[0].Attachments[0].Content) != "png-bytes" {
		t.Errorf("forwarded %+v", sent)
	}
}

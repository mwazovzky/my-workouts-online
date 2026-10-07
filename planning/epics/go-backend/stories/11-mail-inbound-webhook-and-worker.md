# User Story: Mail, Inbound Webhook and Worker

Send real mail through Resend, accept the inbound support-email webhook, and forward received messages from a durable River worker.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Auth & Profile (verification and reset mail now leave the `log` adapter)
- Creates New Feature: No

## Problem Statement

Story 5 wired `ports.Mailer` with a `log` adapter only, so verification and reset mail never leave the process. Production also receives support email: Resend posts `email.received` events to `POST /api/webhooks/resend`, and the PHP service (`app/Http/Middleware/VerifyResendWebhookSignature.php`, `app/Http/Controllers/Api/Webhooks/ResendWebhookController.php`) enqueues `app/Jobs/ForwardInboundEmail.php`, which fetches the message and attachments through `app/Services/InboundEmail/ResendInboundEmailSource.php` and sends `app/Mail/ForwardedInboundEmail.php` to a personal inbox. The queue is the database and a `worker` container drains it. The Go service needs the same three pieces: an outbound adapter, the webhook, and a background job that survives restarts.

## User-Visible Outcome

None for app users. Support email sent to the production domain reaches the inbox whichever backend handles the webhook.

## Scope

- `internal/platform/mail/resend`: `Mailer` implementation on `resend-go/v2`, `From` = `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME`, attachments supported; selected by `MAIL_MAILER=resend|log|fake` in `platform/config`.
- `POST /api/webhooks/resend` in `internal/platform/httpapi` (outside `/api/v1`, no bearer): Svix signature verification with the `svix-webhooks` Go package against `RESEND_WEBHOOK_SECRET` (`svix-id`, `svix-timestamp`, `svix-signature`, 300 s tolerance); 403 `forbidden` when the secret is blank or the signature fails; always 204 otherwise; for `type == "email.received"` with `data.email_id`, insert an `inbound_emails` idempotency row (`email_id` primary key, `received_at`, pruned after one week) and enqueue the job with River's `InsertTx` in the same transaction; a duplicate id is a no-op.
- `internal/platform/jobs`: River client setup (`riverqueue/river`, Postgres driver on the shared pool), `ForwardInboundEmailArgs{EmailID}` with `MaxAttempts 5` and a custom backoff of 60, 300, 900 s; `workouts work` subcommand starts the workers with graceful stop on signal; River's own migrations are already applied by `workouts migrate` (story 2).
- `ForwardInboundEmail` worker: skip with a `slog` warning when `MAIL_INBOUND_FORWARD_TO` is blank; fetch the email via `ports.InboundEmailSource` (Resend receiving API; attachments downloaded with a 30 s `http.Client` timeout); send with From `"<sender name or address> via <APP_NAME>"` at the app address, Reply-To the original `reply_to` or `from`, subject kept or `(no subject)`, HTML body = summary header (From, To, Cc, Date) + original HTML or the escaped text, original attachments re-attached; a transient fetch error returns an error so River retries; a permanent one (404 from Resend) cancels the job.
- Fake Resend server (`httptest.Server`) in tests for both the receiving API and the send API; `fake` mailer records messages for assertions.
- `docker-compose.yml` and `docker-compose.prod.yml`: `worker-go` service running `workouts work` from the same image, `mem_limit` 64m; `.env.example` documents `MAIL_MAILER`, `RESEND_KEY`, `RESEND_WEBHOOK_SECRET`, `MAIL_INBOUND_FORWARD_TO` as shared with PHP.

## Non-Goals

- replacing PHP as the production webhook target (nginx still routes to PHP; story 13 adds the switch)
- templates beyond verification, reset and the forwarded message
- outbound mail retries (verification and reset stay synchronous like PHP)

## Acceptance Criteria

- a webhook request with a valid Svix signature and `email.received` returns 204 and leaves one River job and one `inbound_emails` row; the same event id posted again returns 204 and adds nothing
- an invalid signature returns 403 with the Problem Details body; a blank secret returns 403 before reading the body
- the worker forwards a message with two attachments to `MAIL_INBOUND_FORWARD_TO`; the fake mailer records From, Reply-To, subject, the summary header and both attachments
- with `MAIL_INBOUND_FORWARD_TO` empty the job completes without sending and logs a warning
- a 500 from the fake Resend receiving API leaves the job in River's retry state with the first backoff of 60 s; a 404 cancels it
- `workouts work` stops within the shutdown timeout on SIGTERM
- `make check` green

## Initial Tasks

- [ ] `resend` mailer adapter and config switch
- [ ] webhook handler with Svix verification and transactional enqueue
- [ ] `inbound_emails` migration and sqlc queries
- [ ] River client, job args, worker, backoff; `workouts work`
- [ ] `ResendInboundEmailSource` port implementation
- [ ] fake Resend server and fake mailer; tests ported from `tests/Feature/InboundEmail/*`
- [ ] compose services, `.env.example`, docs

## Test Cases

- unit: forwarded-message builder (From display name, Reply-To fallback, `(no subject)`, HTML vs text body, attachment pass-through)
- HTTP: webhook signature valid/invalid/missing secret; idempotency; non-`email.received` events ignored
- worker (testcontainers): job runs end to end against the fake Resend server; retry and cancel paths; blank forward address
- lifecycle: graceful stop of `workouts work`

## Docs To Update

- `docs/deployment.md` (Support inbox section: Go worker, shared env vars)
- `docs/architecture.md` (platform mail and jobs)
- `docs/features/auth-and-profile.md` (mail delivery note for the Go backend)

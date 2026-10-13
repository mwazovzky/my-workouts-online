# 0013. Support Inbox: Resend Client, Webhook Idempotency and River Jobs

- Status: accepted
- Date: 2026-10-13
- Story: [Go epic story 11](../../../../planning/epics/go-backend/stories/11-mail-inbound-webhook-and-worker.md)

## Context

Production receives support email: Resend posts an `email.received` event to `POST /api/webhooks/resend`, signed with Svix. PHP checks the signature (`VerifyResendWebhookSignature`, 403 for a bad one and for every request while `RESEND_WEBHOOK_SECRET` is blank), queues `ForwardInboundEmail` and answers 204. The job fetches the email and its attachments from Resend's receiving API and sends it to `MAIL_INBOUND_FORWARD_TO` from the app's own address, with up to 5 attempts and a backoff of 60, 300 and 900 seconds. Resend may deliver a webhook more than once, so the job caches a forwarded id for a week.

ADR 0002 planned `resend-go` and the `svix` package for this story, to be confirmed or replaced here, and installed River's schema in migration 00004. Two findings changed the plan:

- `resend-go` (v3.17) turns every non-2xx answer except 429 into `errors.New("[ERROR]: " + message)`. The worker must cancel a job for an email Resend does not know (404) and retry an outage (5xx), which the library's errors cannot tell apart without parsing their wording.
- The `svix` Go module is a full API client. The service needs only the signature check: an HMAC-SHA256 over `{svix-id}.{svix-timestamp}.{body}`, compared in constant time, with a timestamp tolerance. Resend's PHP `WebhookSignature::verify` is 50 lines.

The webhook table also needs an owner. ADR 0001 gives every table to a module, and `platform` knows no table.

## Considered Options

1. `resend-go` and `svix`, telling a 404 from a 5xx by matching the error text.
2. A small client in `platform/resend` on `net/http` for the three calls (send, get received email, list attachments) plus the attachment download, returning `*APIError{Status}`; the signature check written against Resend's PHP verifier.
3. Deduplicate in the job after sending, as PHP's cache does, instead of in the webhook.
4. Record the email id in the webhook's transaction and insert the River job in that same transaction.

## Decision

Options 2 and 4.

- **Client.** `platform/resend` is the client and implements `kernel/mail.Mailer` (`MAIL_MAILER=resend`). It sends the API key only to the API: attachment download URLs are pre-signed and on another host. Downloads time out after 30 seconds, as PHP's `Http::timeout(30)`, and are capped at Resend's 40 MB limit.
- **Signature check.** `inbox/adapters/http` verifies the signature with PHP's rules and failure messages ("Missing required headers", "Message timestamp too old", "No signatures found matching the expected signature"), each the `detail` of a 403 `forbidden` Problem. The tests sign with the secret and formula of PHP's `ResendWebhookTest`. No new module enters `go.mod`; River's `riverpgxv5` driver moves from indirect to direct.
- **Module.** A new module, `inbox`, owns `inbound_emails` (`migrations/00005_inbox.sql`) and depends on no other module:
  - `app.Receive` prunes ids older than a week, records the id, and only for a new id inserts the job with River's `InsertTx`, all in one transaction. A redelivered webhook therefore queues nothing, and a job exists exactly when its id is recorded.
  - `app.Forward` is the job body. `domain.Forward` builds the message as `ForwardedInboundEmail` does: From name "<sender name or address> via <APP_NAME>", Reply-To the original Reply-To or the sender, `(no subject)`, then a summary of From, To, Cc and Date over the original HTML or the escaped text, with the original attachments.
- **Jobs.**
  - `platform/jobs` builds the River client on the shared pool. It is insert-only in `serve` and working in `workouts work`, which stops on SIGTERM: running jobs get `SHUTDOWN_TIMEOUT` to finish, then are cancelled and retried later. River logs from info up.
  - `inbox/adapters/jobs` defines the job: 5 attempts, retry after 60, 300, then 900 seconds per attempt, timed on the wall clock (River ignores a retry time before its own now). A 404 from Resend cancels the job (`river.JobCancel`); any other error retries it.
- **Configuration.** Shared with PHP: `MAIL_MAILER` (`log` or `resend`; anything else stops start-up), `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` (default `APP_NAME`), `MAIL_INBOUND_FORWARD_TO`, `RESEND_KEY`, `RESEND_WEBHOOK_SECRET`. `RESEND_BASE_URL` points tests at a fake server. A malformed webhook secret stops start-up instead of rejecting every request.

## Consequences

- Good: the webhook's 403 Problem has the same fields and wording as PHP's (compared on the dev stack, where no secret is set), and the worker is tested end to end against a fake Resend server (`platform/resend/resendtest`): forward with attachments, a blank forward address, retry on a 500 with the 60-second backoff, cancel on a 404.
- Good: a redelivered webhook is a no-op in the database, not a cache read in the job, and the job cannot be lost between the record and the insert.
- Bad: deduplication is at the webhook, not after sending. A job that fails all 5 attempts, or is cancelled, is not queued again by a redelivery within the week; PHP would try again. Its failure stays visible in `river_job` and the worker log, and the email in the Resend dashboard.
- Bad: three Resend endpoints are maintained here; a change to their JSON shapes means a change to `platform/resend`.
- Follow-up: story 13 adds `worker-go` to the production compose file (`mem_limit` 64m, `go` profile) and routes the webhook to Go when `API_BACKEND=go`; until then nginx sends it to PHP.

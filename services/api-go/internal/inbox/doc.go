// Package inbox is the support inbox module: email received on the
// production domain is relayed to a personal mailbox. Resend posts an
// email.received webhook; the module records the email id (a redelivered
// webhook queues nothing) and queues a River job in the same transaction; the
// worker (`workouts work`) fetches the email with its attachments and
// forwards it (ADR 0013). It owns the inbound_emails table
// (migrations/00005_inbox.sql) and depends on no other module.
//
//	domain/            Email, Attachment and Forward, which builds the relayed message
//	ports/             the email source, the receipt store and the job queue
//	app/               the use cases: Receive (webhook) and Forward (worker)
//	adapters/http      POST /api/webhooks/resend with Svix signature verification
//	adapters/postgres  the receipts over sqlc (sql/inbox)
//	adapters/resend    the email source over the Resend receiving API
//	adapters/jobs      the River job: its args, worker, retry backoff and queue
package inbox

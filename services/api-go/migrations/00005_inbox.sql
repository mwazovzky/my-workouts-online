-- Inbox: the email ids the Resend webhook has queued for forwarding. The inbox
-- module owns this table. A webhook can be delivered more than once; a
-- delivery whose id is already here queues nothing. Rows older than a week
-- are pruned by the webhook itself, as PHP forgets a forwarded id after a week.

-- +goose Up
CREATE TABLE inbound_emails (
    email_id    text        PRIMARY KEY,
    received_at timestamptz NOT NULL
);

CREATE INDEX inbound_emails_received_at_idx ON inbound_emails (received_at);

-- +goose Down
DROP TABLE inbound_emails;

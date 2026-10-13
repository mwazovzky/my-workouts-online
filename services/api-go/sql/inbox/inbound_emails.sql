-- name: RecordInboundEmail :execrows
-- One row per email id: 1 when the id is new, 0 when it was already recorded.
INSERT INTO inbound_emails (email_id, received_at)
VALUES (@email_id, @received_at)
ON CONFLICT (email_id) DO NOTHING;

-- name: PruneInboundEmails :exec
DELETE FROM inbound_emails
WHERE received_at < @before;

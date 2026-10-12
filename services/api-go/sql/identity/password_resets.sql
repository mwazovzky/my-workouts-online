-- name: PutPasswordReset :exec
-- One pending reset per email: a new request replaces the previous token.
INSERT INTO password_reset_tokens (email, token_hash, created_at)
VALUES (@email, @token_hash, @created_at)
ON CONFLICT (email) DO UPDATE
SET token_hash = EXCLUDED.token_hash, created_at = EXCLUDED.created_at;

-- name: GetPasswordReset :one
SELECT * FROM password_reset_tokens
WHERE email = @email;

-- name: DeletePasswordReset :exec
DELETE FROM password_reset_tokens
WHERE email = @email;

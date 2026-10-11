-- name: CreateRefreshToken :one
INSERT INTO refresh_tokens (user_id, family_id, token_hash, device_name, authenticated_at, expires_at, created_at, updated_at)
VALUES (@user_id, @family_id, @token_hash, @device_name, @authenticated_at, @expires_at, @now, @now)
RETURNING *;

-- name: GetRefreshTokenByHash :one
SELECT * FROM refresh_tokens
WHERE token_hash = @token_hash;

-- name: LockRefreshToken :one
-- Rotation holds this lock until it commits, so of two concurrent
-- refreshes of one token exactly one sees it unrotated.
SELECT * FROM refresh_tokens
WHERE id = @id
FOR UPDATE;

-- name: MarkRefreshTokenReplaced :exec
UPDATE refresh_tokens
SET replaced_by_id = @replaced_by_id::bigint, revoked_at = @now::timestamptz, updated_at = @now::timestamptz
WHERE id = @id;

-- name: RevokeRefreshFamily :exec
UPDATE refresh_tokens
SET revoked_at = @now::timestamptz, updated_at = @now::timestamptz
WHERE family_id = @family_id AND revoked_at IS NULL;

-- name: StepUpRefreshFamily :exec
-- Reauthentication moves the family's authentication time forward, so the
-- access tokens later refreshes issue carry the new auth_time.
UPDATE refresh_tokens
SET authenticated_at = @now, updated_at = @now
WHERE family_id = @family_id AND user_id = @user_id AND revoked_at IS NULL;

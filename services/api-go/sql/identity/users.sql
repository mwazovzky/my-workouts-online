-- name: CreateUser :one
INSERT INTO users (name, email, password_hash)
VALUES (@name, @email, @password_hash)
RETURNING *;

-- name: UpsertVerifiedUser :one
-- The demo admin of `workouts seed`: created, or reset to the known
-- password and verified, matching the email without regard to case.
INSERT INTO users (name, email, password_hash, email_verified_at)
VALUES (@name, @email, @password_hash, @verified_at::timestamptz)
ON CONFLICT ((lower(email))) DO UPDATE
SET name = EXCLUDED.name,
    password_hash = EXCLUDED.password_hash,
    email_verified_at = EXCLUDED.email_verified_at,
    updated_at = now()
RETURNING *;

-- name: GetUserByEmail :one
SELECT sqlc.embed(users),
       EXISTS (
           SELECT 1 FROM role_user
           JOIN roles ON roles.id = role_user.role_id
           WHERE role_user.user_id = users.id AND roles.name = 'Admin'
       ) AS is_admin
FROM users
WHERE lower(users.email) = lower(@email);

-- name: GetUserByID :one
SELECT sqlc.embed(users),
       EXISTS (
           SELECT 1 FROM role_user
           JOIN roles ON roles.id = role_user.role_id
           WHERE role_user.user_id = users.id AND roles.name = 'Admin'
       ) AS is_admin
FROM users
WHERE users.id = @id;

-- name: GrantRole :execrows
INSERT INTO role_user (user_id, role_id)
SELECT @user_id, roles.id FROM roles WHERE roles.name = @role_name
ON CONFLICT DO NOTHING;

-- name: UpdateUser :one
UPDATE users
SET name = @name,
    email = @email,
    locale = @locale,
    theme_preference = @theme_preference,
    email_verified_at = sqlc.narg(email_verified_at)::timestamptz,
    updated_at = now()
WHERE id = @id
RETURNING *;

-- name: MarkUserVerified :exec
UPDATE users
SET email_verified_at = @now::timestamptz, updated_at = @now::timestamptz
WHERE id = @id AND email_verified_at IS NULL;

-- name: SetUserPassword :exec
UPDATE users
SET password_hash = @password_hash, updated_at = now()
WHERE id = @id;

-- name: DeleteUser :exec
-- Workouts, enrollments, roles and refresh tokens go with the user
-- (ON DELETE CASCADE).
DELETE FROM users
WHERE id = @id;

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

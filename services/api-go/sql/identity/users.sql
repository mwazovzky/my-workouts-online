-- name: CreateUser :one
INSERT INTO users (name, email, password_hash)
VALUES (@name, @email, @password_hash)
RETURNING *;

-- name: GetUserByEmail :one
SELECT * FROM users
WHERE lower(email) = lower(@email);

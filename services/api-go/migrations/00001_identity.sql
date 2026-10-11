-- Identity: users, roles, refresh tokens and password resets. The identity
-- module owns these tables; catalog and training reference users(id) only.

-- +goose Up
CREATE TABLE users (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name              text        NOT NULL,
    email             text        NOT NULL,
    email_verified_at timestamptz,
    password_hash     text        NOT NULL,
    -- An open set (the locales the API ships), validated by the application.
    locale            text        NOT NULL DEFAULT 'en',
    theme_preference  text        NOT NULL DEFAULT 'system'
        CONSTRAINT users_theme_preference_check CHECK (theme_preference IN ('light', 'dark', 'system')),
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now()
);

-- Case-insensitive, as the Laravel API gets from its MySQL collation.
CREATE UNIQUE INDEX users_email_key ON users (lower(email));

CREATE TABLE roles (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name        text        NOT NULL CONSTRAINT roles_name_key UNIQUE,
    description text,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);

-- The application checks roles by name, so every environment has them.
INSERT INTO roles (name, description) VALUES
    ('Admin', 'Administrator role with full access'),
    ('User', 'Regular user role with limited access');

CREATE TABLE role_user (
    user_id    bigint      NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    role_id    bigint      NOT NULL REFERENCES roles (id) ON DELETE CASCADE,
    created_at timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT role_user_pkey PRIMARY KEY (user_id, role_id)
);

CREATE INDEX role_user_role_id_idx ON role_user (role_id);

CREATE TABLE refresh_tokens (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id          bigint      NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    family_id        uuid        NOT NULL,
    -- hex(sha256(token)); the token itself is never stored.
    token_hash       text        NOT NULL CONSTRAINT refresh_tokens_token_hash_key UNIQUE,
    device_name      text,
    authenticated_at timestamptz NOT NULL,
    expires_at       timestamptz NOT NULL,
    revoked_at       timestamptz,
    replaced_by_id   bigint      REFERENCES refresh_tokens (id) ON DELETE SET NULL,
    created_at       timestamptz NOT NULL DEFAULT now(),
    updated_at       timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX refresh_tokens_family_id_idx ON refresh_tokens (family_id);
CREATE INDEX refresh_tokens_user_id_idx ON refresh_tokens (user_id);
CREATE INDEX refresh_tokens_replaced_by_id_idx ON refresh_tokens (replaced_by_id);

CREATE TABLE password_reset_tokens (
    email      text        PRIMARY KEY,
    -- hex(sha256(token)); the token itself is never stored.
    token_hash text        NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- +goose Down
DROP TABLE password_reset_tokens;
DROP TABLE refresh_tokens;
DROP TABLE role_user;
DROP TABLE roles;
DROP TABLE users;

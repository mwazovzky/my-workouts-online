# User Story: Token Issuer and JWT Guard

Make the API issue and verify its own short-lived JWT access tokens and rotating refresh tokens, replacing Sanctum.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: Platform
- Creates New Feature: No

## Problem Statement

Sanctum personal access tokens never expire, cannot be rotated, and the stateful cookie flow is Laravel-specific. A replaceable backend needs a standard token any implementation can verify.

## User-Visible Outcome

Sessions silently renew while the user is active and expire safely when the refresh token is stolen or stale.

## Scope

- `refresh_tokens` table: `id`, `user_id`, `family_id`, `token_hash`, `expires_at`, `revoked_at`, `replaced_by_id`, `device_name`, timestamps
- asymmetric signing key pair loaded from config / env; `GET /.well-known/jwks.json`
- `app/Services/Auth/TokenService` behind an interface: `issuePair()`, `refresh()`, `revokeFamily()`, `verifyAccessToken()`
- access token claims: `iss`, `aud`, `sub`, `iat`, `exp`, `jti`, `auth_time`
- custom `jwt` guard (`Auth::viaRequest`); API routes switch from `auth:sanctum` to `auth:jwt,web` so the Inertia frontend's session keeps working until story 10 removes it
- refresh rotation: every refresh issues a new refresh token and marks the old one replaced; reuse of a replaced token revokes the whole family
- `POST /auth/login`, `POST /auth/refresh`, `POST /auth/logout` (moved here from story 3 because the issuer cannot be exercised over HTTP without them); `client: web | native` decides cookie vs body delivery
- Sanctum personal access tokens removed (`POST /tokens`, `HasApiTokens`, table dropped); the Sanctum package stays only for `EnsureFrontendRequestsAreStateful` until story 10
- existing API tests keep using `actingAs` (session guard); JWT-specific tests use `withToken()`

## Non-Goals

- auth endpoints themselves (story 3)
- social login

## Acceptance Criteria

- an access token verifies via the JWKS document with a standard library
- a refresh returns a new pair and invalidates the old refresh token
- reusing a rotated refresh token revokes the family and returns 401 `refresh_token_reused`
- an expired access token returns 401 `token_expired`
- no API route authenticates with `auth:sanctum`; Sanctum personal access tokens no longer exist

## Initial Tasks

- migration and model for refresh tokens
- key generation command and config keys
- token service, JWKS controller, guard
- login / refresh / logout controller
- swap middleware on `routes/api.php`

## Test Cases

- issue pair, verify access token, a protected route succeeds
- refresh rotates and old token fails
- reuse detection revokes family
- expired access token rejected with correct code
- tampered signature rejected
- JWKS document exposes the public key only

## Docs To Update

- docs/architecture.md (auth section)
- docs/features/auth-and-profile.md

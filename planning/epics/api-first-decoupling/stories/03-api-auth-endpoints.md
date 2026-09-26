# User Story: API Auth Endpoints

Expose registration, login, logout, refresh, password reset, email verification, and step-up re-authentication as JSON endpoints so no client depends on Breeze web routes.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: Platform
- Creates New Feature: No

## Problem Statement

All auth flows are Inertia pages with redirects and session flash. A standalone SPA or an iOS app cannot use them.

## User-Visible Outcome

Users can register, log in, reset their password, verify their email, and re-authenticate for sensitive actions from any client. Emails link to the SPA.

## Scope

- `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`, `POST /auth/refresh`
- `POST /auth/password/forgot`, `POST /auth/password/reset`
- `POST /auth/email/verify`, `POST /auth/email/resend`
- `POST /auth/reauth` returning a fresh access token with updated `auth_time`
- login/register accept `client: web | native`; web gets the refresh token as an httpOnly cookie scoped to `/api/v1/auth/refresh`, native gets it in the body
- refresh endpoint requires a custom header (`X-Requested-With`) and validates `Origin` for the cookie path
- controllers in `app/Http/Controllers/Api/Auth/` reuse `LoginRequest`, the password broker, and verification notifications
- enable `MustVerifyEmail` on `User`; verification and reset links point at `config('app.frontend_url')` and carry single-use tokens
- per-IP and per-account throttles on every auth endpoint
- `POST /api/v1/tokens` (Sanctum) removed

## Non-Goals

- the SPA pages (story 8)
- social login endpoints (designed in the contract, not built)

## Acceptance Criteria

- every endpoint matches `contracts/openapi.yaml`
- verification email contains a frontend URL; posting its token verifies the user
- password reset email contains a frontend URL; posting token + password resets it
- `reauth` with the wrong password fails; with the right one returns a token whose `auth_time` is recent
- unverified users are rejected from `verified` routes with 403 `email_unverified`
- throttled requests return 429 with the error envelope

## Initial Tasks

- controllers and form requests
- notification URL customisation
- `frontend_url` config key and env
- throttle definitions
- remove `TokenController` and its request

## Test Cases

- register issues a pair and sends a verification email
- login with bad credentials returns 422 with a coded error
- web client receives the refresh cookie and no refresh token in the body
- native client receives the refresh token in the body and no cookie
- logout revokes the refresh family
- forgot password sends the frontend link; reset succeeds once and fails on reuse
- verify succeeds with a valid token and fails with an expired one
- reauth updates `auth_time`
- throttle triggers after the configured attempts

## Docs To Update

- docs/features/auth-and-profile.md
- docs/pages-and-routes.md

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

- `POST /auth/register` (login, refresh and logout shipped in story 2)
- `POST /auth/password/forgot`, `POST /auth/password/reset`
- `POST /auth/email/verify`, `POST /auth/email/resend`
- `POST /auth/reauth` returning a fresh access token with updated `auth_time`
- register accepts `client: web | native` and responds like login
- controllers in `app/Http/Controllers/Api/Auth/` reuse `LoginRequest`, the password broker, and verification notifications
- enable `MustVerifyEmail` on `User` and backfill existing accounts as verified; verification and reset links point at `config('app.frontend_url')` and carry single-use tokens
- `verified` alias replaced by `App\Http\Middleware\EnsureEmailIsVerified` so JSON clients get 403 `email_unverified`
- per-IP and per-account throttles on every auth endpoint

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

## Test Cases

- register issues a pair and sends a verification email
- forgot password sends the frontend link; reset succeeds once and fails on reuse
- verify succeeds with a valid token and fails with an expired one
- reauth updates `auth_time`
- throttle triggers after the configured attempts

## Docs To Update

- docs/features/auth-and-profile.md
- docs/pages-and-routes.md

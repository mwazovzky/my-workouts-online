# User Story: Identity: Verification, Reset and Me

Complete the `identity` module with email verification, password reset, outgoing mail behind a port, the `/me` resource and the `verified` gate.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: Platform (mailer port)
- Creates New Feature: No

## Problem Statement

After story 4 a user can sign in but cannot verify an email, recover a password or edit a profile, and every `verified` route is unreachable. PHP builds verification links with Laravel's `temporarySignedRoute` (HMAC over the full API URL with `APP_KEY`) in `app/Auth/EmailVerificationLink.php`; both clients only post `{id, hash, expires, signature}` back, so the Go service is free to sign links its own way as long as it generates them itself. Mail must go through a port so tests and the dev stack never send anything.

## User-Visible Outcome

None for the web SPA and iOS app. Against the Go service a user receives a verification email, verifies through the SPA's `/verify-email/:id/:hash` route, resets a forgotten password through `/reset-password/:token`, and edits name, email, locale, theme and password.

## Scope

- `internal/identity/adapters/links`: `LinkSigner` with `signature = hex(HMAC-SHA256(LINK_SIGNING_KEY, "verify|{id}|{hash}|{expires}"))`, `hash = hex(sha256(lowercase email))`, 60-minute expiry, constant-time comparison. `docs/adr/0008-go-native-signed-links.md` records why Laravel's scheme is not reproduced (coupling to `APP_KEY` and route canonicalisation for no client benefit).
- `verifyEmail`: 403 `invalid_verification_link` when `id`/`hash` do not match the signed-in user, 403 `invalid_signature` when the signature or expiry fails, 204 on success and when already verified; throttled 6/min. `resendVerification`: 202 always; throttled 6/min.
- `forgotPassword`: 202 regardless of whether the email exists, random 32-byte token stored sha256-hashed in `password_reset_tokens`, 60-minute expiry, one pending token per email, throttled 6/min; mail links to `{FRONTEND_URL}/reset-password/{token}?email=`. `resetPassword`: validates token and expiry, sets the new password, revokes every refresh family, 204; invalid or expired token → 422 pointer `/token` code `invalid_value`.
- `ports.Mailer{Send(ctx, Message) error}` with `internal/platform/mail/log` (slog) and `mail/fake` (captures for tests); `html/template` bodies for verify and reset in `en` and `ru`, rendered in the request locale, embedded. The Resend adapter arrives in story 11.
- `GET /me`, `PATCH /me` (merge patch: `name`, `email`, `locale`, `theme_preference` all optional; an email change clears `email_verified_at` and sends a new verification mail), `PUT /me/password` (`current_password` check, revoke every family except the current one), `DELETE /me` (password check, revoke all, delete the user; cascades through the schema).
- `verified` gate: operations tagged as requiring verification (the same set PHP wraps in the `verified` middleware) return 403 `email_unverified` for an unverified principal.
- `User` representation: `id`, `name`, `email`, `locale`, `theme_preference`, `is_admin`, `email_verified_at`.
- Scope file gains `forgotPassword resetPassword verifyEmail resendVerification getMe updateMe updatePassword`. `deleteMe` is covered by `httptest` only; the script excludes it because it would delete the seeded account.

## Non-Goals

- Resend delivery (story 11)
- the inbound support webhook (story 11)
- changing what the emails say or where the links point

## Acceptance Criteria

- a verification mail captured by the fake mailer contains a link the SPA's `VerifyEmail.vue` posts back successfully to `:8081`; tampering with any of the four fields yields 403 `invalid_signature`
- a reset link from the fake mailer completes a reset through `ResetPassword.vue`; the old refresh cookie is then rejected with `refresh_token_invalid`
- `PATCH /me {"locale":"ru"}` changes only the locale; `PATCH /me {}` is a 200 no-op
- an unverified user receives 403 `email_unverified` on `GET /api/v1/dashboard` and 200 on `GET /me`
- `contract-go` is green for the widened scope
- every test method in `tests/Feature/Api/Auth/{EmailVerification,PasswordReset}Test.php` and `tests/Feature/Api/{Me,Profile}Test.php` has a Go counterpart

## Initial Tasks

- [ ] `LinkSigner` and ADR 0008
- [ ] verification and resend use cases, throttles
- [ ] password reset repository, use cases, mail template
- [ ] mailer port, log and fake adapters, templates in `en` and `ru`
- [ ] `/me` handlers and use cases; `verified` gate in the policy middleware
- [ ] scope file, golden files, manual run of both clients against `:8081`

## Test Cases

- signer: valid, expired, wrong user, wrong email, tampered signature, constant-time compare
- reset: unknown email still 202; expired token; token reuse; all families revoked after reset
- `/me`: merge-patch partial updates, email uniqueness → 422 pointer `/email` code `not_unique`, password change revokes other families only, delete cascades workouts and tokens
- mail: locale selection, link format, no send on `resend` when already verified
- `verified` gate on each gated operation; ungated operations unaffected

## Docs To Update

- `docs/features/auth-and-profile.md` (Go link-signing note; `/me` behaviour unchanged)
- `docs/architecture.md` (mailer port, link signing)

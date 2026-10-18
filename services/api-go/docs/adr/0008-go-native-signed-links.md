# 0008. Go-Native Signed Links, Mail behind a Port, and the Verified Gate

- Status: accepted
- Date: 2026-10-12
- Story: [Go epic story 05](../../../../planning/epics/go-backend/stories/05-identity-verification-reset-and-me.md)

## Context

Email verification and password reset send a link to the SPA (`/verify-email/{id}/{hash}?expires&signature`, `/reset-password/{token}?email`). The SPA, and the iOS app through a universal link, posts the values back to the API. The PHP API signs verification links with Laravel's `URL::temporarySignedRoute`, an HMAC with `APP_KEY` over the full API URL of a named route, and then swaps the host for `FRONTEND_URL`. Its hash is `sha1(email)`. Reset tokens are random, stored as a bcrypt hash by Laravel's password broker.

The clients never inspect these values; they only carry them back to the server that issued them. Reproducing Laravel's scheme in Go would mean sharing `APP_KEY` and copying Laravel's URL canonicalization for no client benefit.

Mail must not leave the dev stack or the test suite, and the Resend adapter is story 11.

Story 4 left one policy open: which operations need a verified email. PHP wraps them in a `verified` route group. The contract does not mark them.

## Considered Options

1. Reproduce Laravel's signed-route scheme with `APP_KEY`.
2. A Go-native HMAC over the four posted values with a key of the Go service's own.
3. For the verified gate: a contract extension (`x-requires-verified-email`), a list of operationIds in code, or a rule over the contract's existing tags.

## Decision

- **Verification links** (`internal/identity/adapters/links`): `hash = hex(sha256(lowercase email))` and `signature = hex(HMAC-SHA256(LINK_SIGNING_KEY, "verify|{id}|{hash}|{expires}"))`, compared in constant time. Links live 60 minutes. The use case checks first that `id` and `hash` belong to the signed-in user (403 `invalid_verification_link`), then the signature and expiry (403 `invalid_signature`), as `EmailVerificationController` does. `LINK_SIGNING_KEY` must be at least 32 characters in production. Elsewhere a fixed development key is used when it is unset, so the dev stack and CI need no secret.
- **Reset tokens:** 32 random bytes, stored as SHA-256 (like refresh tokens), one per lowercased email in `password_reset_tokens`, valid 60 minutes, deleted when used. A reset sets the password and revokes every refresh family. An unknown, used or expired token is 422 on `/token`; both clients display a `token` error.
- **Mail:** the port is `kernel/mail.Mailer`. `platform/mail.Log` writes each message, link included, to the structured log (the dev stack and CI, as `MAIL_MAILER=log` does for PHP). `platform/mail.Fake` records messages for tests, which follow the links as the SPA would. Identity's `notify` adapter renders embedded `text/template` and `html/template` bodies in English and Russian in the request locale. Mail failures are logged, never returned, so register, resend and forgot answer the same whatever the mail provider does.
- **Verified gate** (`internal/platform/httpapi/policy.go`): an operation needs a verified email when it needs a bearer token and is tagged neither `Auth` nor `Me`. `TestPolicyMatchesThePHPRouteGroups` proves that this rule yields exactly PHP's `verified` group. An unverified caller gets 403 `email_unverified`, checked before the admin check, as in PHP.

Option 1 was rejected for the coupling. A contract extension would add a second source of truth for what the tags already say, and an id list in code would drift from the contract.

Departures from the PHP API:

| Case | PHP | Go |
| --- | --- | --- |
| Verification link `hash` | `sha1(email)` | `sha256(lowercase email)`; links from one backend do not verify on the other |
| Invalid reset token | 422 on `/email` | 422 `invalid_value` on `/token` |
| Changing the email | Clears `email_verified_at` | Clears it and emails a verification link to the new address |
| `PATCH /me {}` | 422 `required` on `/name` (`required_without_all`) | 422 `required` on the whole body (the contract's `minProperties: 1`) |
| Email texts | Laravel's default English texts in every locale | English and Russian templates, chosen by `Accept-Language` |
| `forgot` while a reset is pending | Laravel throttles a new token for 60 seconds | A new request replaces the pending token; the route throttle (6/min) bounds it |

Aligned in [story 15](../../../../planning/epics/go-backend/stories/15-php-parity-fixes.md): PHP now emails a verification link to a changed address.

## Consequences

- Good: no secret or URL scheme is shared with Laravel; the link format the clients parse is unchanged.
- Good: making an operation verified-only is a change of tag in the contract; the gate follows.
- Bad: a link emailed by one backend is not accepted by the other. Accepted: users and tokens are per database anyway, and a backend switch already requires signing in again.
- Bad: the log mailer writes reset and verification links to the logs. It is meant for development and CI only; production uses the Resend adapter (story 11).
- Follow-up: story 11 adds the Resend adapter behind the same port.

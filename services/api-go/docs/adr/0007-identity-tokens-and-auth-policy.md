# 0007. Token Compatibility with the PHP API, and Auth Policy from the Contract

- Status: accepted
- Date: 2026-10-11
- Story: [Go epic story 04](../../../../planning/epics/go-backend/stories/04-identity-tokens-login-refresh-and-jwks.md)

## Context

The web SPA and the iOS app already speak the PHP API's token protocol:

- Ed25519 access tokens (`kid` header; claims `iss`, `aud`, `sub`, `jti`, `iat`, `exp`, `auth_time`, `fam`; 15 minutes, 30 s leeway), published at `/.well-known/jwks.json`;
- opaque refresh tokens stored as SHA-256, rotating within a family that keeps its authentication time;
- reuse detection that revokes the family;
- web clients get the refresh token in an httpOnly cookie scoped to the refresh endpoint, native clients in the body;
- the clients refresh on `token_expired` and sign out on the `refresh_token_*` codes.

For the Go service to be a drop-in backend (epic decision 1), none of this may change on the wire. Nothing may require new client code, and both backends must be able to run with one key pair.

Every protected operation also needs a policy: public, signed in, or admin. In PHP that lives in route groups (`auth:jwt`, `admin`) and per-route `throttle:` middleware. The contract already says it: `security: []` marks the public operations, every other operation inherits `bearerAuth`, and the admin operations are tagged `Admin`.

## Considered Options

1. Hand-roll EdDSA JWTs with `crypto/ed25519`. Compact serialization of one algorithm is a few dozen lines.
2. `lestrrat-go/jwx/v3` for signing, parsing and claim validation.
3. Declare the policy per handler (each handler checks the principal).
4. Derive the policy from the embedded contract in one middleware, with throttles in a per-module table keyed by operationId.

## Decision

Options 2 and 4.

- **Tokens** (`internal/identity/adapters/jwt`): jwx 3.3.0 with `EdDSA`. The keys are the base64 raw keys `php artisan jwt:keys` prints: the 64-byte secret key, which is Go's `ed25519.PrivateKey` byte for byte, and the 32-byte public key. `workouts keys generate` prints the same format. `kid` is `JWT_KEY_ID`, or the first 16 hex characters of the public key's SHA-256, as `TokenService.php` derives it. One audience serializes as a string (jwx's per-token `FlattenAudience`), as lcobucci/jwt writes it. Verification requires `sub`, `exp`, `fam` and `auth_time`, with `JWT_LEEWAY` skew. Tests prove compatibility against a token and a JWKS document minted by the PHP `TokenService` with the phpunit key pair: Go verifies the PHP token, the header and claim names and JSON types match, and the JWKS documents are equal. The only remaining difference: PHP writes `iat` and `exp` with fractions of a second, Go as integers; both are JSON numbers.
- **Refresh families** (`internal/identity/app`) follow `TokenService.php` step by step:
  - a rotated token presented again revokes the family (`refresh_token_reused`);
  - revoked is `refresh_token_invalid`, expired is `refresh_token_expired`;
  - rotation locks the row (`SELECT … FOR UPDATE`) inside the Transactor, so of two concurrent refreshes exactly one succeeds and the other counts as reuse;
  - reauth moves the family's authentication time forward, so later refreshes carry the new `auth_time`.
- **Policy** (`internal/platform/httpapi/policy.go`): the `authorize` middleware indexes the embedded spec once. An operation with `security: []` is public. Any other needs a bearer token, resolved by identity's `Authenticate`: the signature is verified, then the user is loaded for `is_admin` and verification. Failures are 401 `unauthenticated` or `token_expired`. An operation tagged `Admin` needs an admin (403 `forbidden`). Throttles are a map from operationId to limit that each module's HTTP adapter exports (`identityhttp.Throttles`: register 10/min, reauth 6/min).
- **Keys missing:** the API still starts. Public operations work, `/health/ready` reports `jwt: down`, and token operations fail. Malformed keys fail at startup.

Option 1 was rejected because the epic chose jwx and claim validation (issuer, audience, skew, required claims) is where hand-written code goes wrong. Option 3 would repeat the contract's security section in Go and let the two drift.

Departures from the PHP API:

| Case | PHP | Go |
| --- | --- | --- |
| Refresh without `X-Requested-With` | 403 from the handler; native refreshes may omit it | 422 `required` on `/X-Requested-With`: the contract makes the header required, and every client already sends it |
| Reauth with a wrong password | 422 `invalid_value` on `/password` | 422 `mismatch`, the contract's code for a password that does not match |
| Register with a short password | 422 `invalid_format` (Laravel's Password rule) | 422 `too_short` |
| Throttle key | Laravel's default: user id, else one key per IP shared by every throttled route | Per operation: user id, else client IP |
| Login for an unknown email | Answers faster than a wrong password | Checks a dummy hash, so both take the same time |
| Reauth without a bearer token | 403 (reachable in PHP tests through `actingAs`) | 401: the authorize middleware stops it first |

oapi-codegen capitalizes operationIds in the spec it embeds (`Register`). The policy index lowercases the first letter, since every contract operationId is lowerCamelCase.

## Consequences

- Good: a key pair can be shared between the backends, so an access token from either verifies on both. Refresh tokens and users are per database, so a switch between backends still forces a new login (epic risk, accepted).
- Good: making an operation public or admin-only is a contract change, and the Go policy follows it with no code change.
- Bad: the policy knows only public, signed-in and admin. The `verified` gate was settled in story 5 by a rule over the tags ([ADR 0008](0008-go-native-signed-links.md)).
- Bad: the login lockout and the throttles live in process memory (`platform/ratelimit.Memory`), so a restart clears them and two instances would not share them. Accepted for one process; a Postgres-backed counter can replace the adapter.
- Follow-up: the acceptance checks against the real clients need `GET /me` and move to story 5. There the web SPA was driven against the Go API (register, verify, profile, logout, reset, sign-in); the iOS replay of Go responses remains open.

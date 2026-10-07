# User Story: Identity: Tokens, Login, Refresh and JWKS

Implement registration, login, refresh-token rotation, logout, step-up re-authentication and the JWKS endpoint in the `identity` module, and turn on the Schemathesis contract job for the Go service.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: Platform (contract CI job, seed command)
- Creates New Feature: No

## Problem Statement

Nothing behind `bearerAuth` can be exercised until the Go service issues and verifies tokens that the web SPA and iOS app accept unchanged: Ed25519 JWTs with the claim set from `app/Services/Auth/TokenService.php`, a `kid` header resolvable through `/.well-known/jwks.json`, opaque refresh tokens that rotate per family with reuse detection, and the two delivery modes (`client=web` cookie, `client=native` body). `scripts/contract-test.sh` logs in before it runs anything, so the contract CI job for Go cannot exist before this story.

## User-Visible Outcome

None for the web SPA and iOS app. A user can register and sign in against the Go service with the same payloads and receive a token pair the existing clients understand.

## Scope

- `internal/identity/domain`: `User`, `Role`, `RefreshToken` (family, hash, expiry, revocation, replacement), `TokenPair`, domain errors.
- `internal/identity/ports`: `UserRepo`, `RefreshTokenRepo`, `Hasher`, `TokenSigner`, `LoginLimiter`, `Clock`.
- `internal/identity/adapters/postgres`: repositories over sqlc queries in `sql/identity`; `refresh` runs inside the Transactor with `SELECT … FOR UPDATE`.
- `internal/identity/adapters/bcrypt`: `x/crypto/bcrypt` cost 12 (verifies Laravel `$2y$` hashes, so a later import stays possible).
- `internal/identity/adapters/jwt`: `lestrrat-go/jwx/v3` signer and verifier; claims `iss`, `aud`, `sub` (user id as a string), `jti`, `iat`, `exp` (`JWT_ACCESS_TTL`, 900 s), `auth_time`, `fam`; `kid` from `JWT_KEY_ID` or the first 16 hex chars of sha256 of the raw public key, exactly as `TokenService.php` computes it; keys read from `JWT_PRIVATE_KEY`/`JWT_PUBLIC_KEY` in the same base64 raw format `php artisan jwt:keys` prints; leeway 30 s; verification requires `sub`, `exp`, `fam`, `auth_time`.
- `GET /.well-known/jwks.json`: OKP Ed25519 JWK with `kid`, `alg: EdDSA`, `use: sig`, `Cache-Control: public, max-age=3600`.
- Use cases: `Register` (lowercased unique email, password policy, immediate token pair, 201), `Login` (401 `invalid_credentials`; lockout after 5 failures per lowercased email + IP → 429 `too_many_attempts` with `Retry-After`), `Refresh` (body token = native path; otherwise cookie path requiring `X-Requested-With: XMLHttpRequest` → 403 when missing; outcomes `refresh_token_missing|invalid|expired|reused`; a reused token revokes its whole family; the new token keeps the original `authenticated_at`), `Logout` (revoke the family in the `fam` claim, clear the cookie, 204), `Reauth` (password re-check, fresh access token with a new `auth_time`, updates the family's `authenticated_at`).
- Cookie: `refresh_token`, `Path=/api/v1/auth/refresh`, `HttpOnly`, `Secure` per `JWT_COOKIE_SECURE`, `SameSite=Strict`, `Max-Age` = `JWT_REFRESH_TTL` (30 days).
- Middleware: bearer (parses the token, sets `kernel/auth.Principal`; distinguishes `token_expired` from `unauthenticated` so `apps/web/src/lib/api.js` and the iOS `AuthMiddleware` refresh correctly) and policy (reads each operation's `security` and tags from the embedded spec: public, authenticated, admin). The `verified` gate follows in story 5.
- Per-route throttles: register 10/min, reauth 6/min, keyed by user id when authenticated else by route + IP, through the rate-limiter port from story 3.
- `workouts seed`: a verified admin `test@example.com`/`password` (refuses when `APP_ENV=production`), plus the `Admin` role grant; `workouts user grant-admin <email>` mirroring `app/Console/Commands/GrantAdminCommand.php`; `workouts keys generate` printing `JWT_PRIVATE_KEY`/`JWT_PUBLIC_KEY` in the PHP format.
- Readiness registers a `jwt` component (keys present and parse).
- `scripts/contract-test.sh`: honour `CONTRACT_INCLUDE` (comma-separated operation ids → `--include-operation-id` flags). `services/api-go/contract-scope.txt` starts with `getJwks register login refresh reauth getMeta`.
- `.github/workflows/contract-go.yml`: Postgres service, `workouts migrate up`, `workouts seed`, `workouts serve &` on `:8081` with `API_RATE_LIMIT_PER_MINUTE=100000` and `JWT_COOKIE_SECURE=false`, wait on `/health`, run the script with the scope file, upload the JUnit report.

## Non-Goals

- email verification, password reset, `/me` (story 5)
- social login (`x-status: target` stays)
- sharing a users table or refresh tokens with the PHP backend

## Acceptance Criteria

- a token issued by Go verifies against Go's JWKS and carries the same claim names and types as a PHP token; `kid` matches PHP for the same key pair
- the web SPA, pointed at `:8081` through a temporary Vite proxy, can register, sign in, survive an expired access token (refresh-and-retry) and log out
- the iOS `WorkoutsCore` `AuthSession` tests pass against a stub transport replaying Go responses captured as golden files
- refresh reuse revokes the family and returns 401 `refresh_token_reused`; concurrent refreshes of the same token produce exactly one success
- `contract-go` is green for the scope file; the job appears on the PR
- `tests/Feature/Api/Auth/{AccessToken,Jwks,Login,Logout,Reauth,Refresh,Register}Test.php` have a Go counterpart for every test method

## Initial Tasks

- [ ] domain, ports, sqlc queries, repositories
- [ ] jwx signer/verifier, JWKS handler, `keys generate`
- [ ] use cases with fakes; bcrypt adapter
- [ ] bearer and policy middleware; cookie handling; throttles and lockout
- [ ] `seed`, `user grant-admin`
- [ ] `CONTRACT_INCLUDE` in the script; `contract-go.yml`; scope file
- [ ] manual check of web and iOS clients against `:8081`

## Test Cases

- claims and `kid` golden-compared against a token minted by PHP with the test key pair in `services/api-php/phpunit.xml`
- refresh: missing, invalid, expired, revoked, reused, concurrent
- cookie attributes on register, login, refresh; cleared on logout
- lockout counter with a fake clock; `Retry-After` present
- policy middleware denies an admin operation to a non-admin with 403 `forbidden`
- `seed` refuses in production

## Docs To Update

- `docs/features/auth-and-profile.md` (Go implementation notes where behaviour is shared; nothing user-facing changes)
- `docs/architecture.md` (identity module, token flow)
- `docs/testing.md` (contract job for Go, scope file)

# Epic: API-First Decoupling

Turn the Inertia monolith into an API-only backend plus independent clients, so that the Vue SPA, a future iOS app, and a future Go backend can each be replaced without touching the others.

## Why This Epic Exists

The product is a Laravel 12 / Inertia v2 / Vue 3 monolith. The backend is already ~70% API (`/api/v1` covers dashboard, programs, workouts, templates, exercises, profile, and admin CRUD) and every page fetches data through axios. What still couples the two halves:

- Breeze session auth (six Inertia auth pages, redirects, flash `status`)
- shared Inertia props (`auth.user`, locale, theme, UI translations)
- Ziggy `route()` and Inertia `Link`/`Head`/`router` across the frontend
- Sanctum stateful-cookie middleware on the `api` group
- session-based guest locale
- email verification effectively disabled (`MustVerifyEmail` commented out)

Removing that coupling is a prerequisite for a second client (iOS) and a second backend (Go).

## Decisions

Cost and effort were explicitly excluded as decision factors. Security and replaceability decide.

| # | Decision | Choice |
| --- | --- | --- |
| 1 | Authentication | The API is the sole token issuer. Short-lived asymmetric JWT access tokens (10-15 min) verifiable via a JWKS endpoint, plus opaque rotating refresh tokens hashed at rest with family-based reuse detection. SPA: refresh token in an httpOnly / Secure / SameSite=Strict cookie scoped to the refresh path, access token in memory only. iOS: both tokens in Keychain. Google and Apple login arrive later as token exchange into the same issuer. Step-up via an `auth_time` claim replaces "confirm password". |
| 2 | Repository layout | Monorepo with strict boundaries: `apps/web`, `services/api-php`, `contracts/openapi.yaml`; later `apps/ios` and `services/api-go`. Path-filtered CI. |
| 3 | Internationalization | Each client owns its UI strings (vue-i18n; iOS String Catalogs). The API translates database content using `Accept-Language` as the authoritative locale; the profile locale is a synced preference. Responses carry `Content-Language` and `Vary: Accept-Language`. Validation errors carry stable codes plus a localized message. Guest locale is client state. |
| 4 | Hosting | Same origin. nginx serves the SPA container and proxies `/api/*` to the API upstream. iOS uses the same hostname. Swapping or partially routing to Go is an nginx change. No CORS. |

Rejected: Sanctum cookie auth for the SPA (Laravel-specific handshake, two mechanisms), bearer tokens in browser storage (XSS exposure), external identity provider (splits identity data, adds a dependency; reconsider only if MFA or SSO become requirements).

## Target Architecture

```
contracts/openapi.yaml     single source of truth; generates TS and Swift clients; contract tests
apps/web/                  Vite + Vue 3 + vue-router + pinia + vue-i18n; static build served by nginx
services/api-php/          the Laravel app, API only (routes/api.php + health)
docker/nginx               / -> web container, /api/* -> api upstream
```

Request flow: clients send `Authorization: Bearer <jwt>` and `Accept-Language`. On a 401 with code `token_expired` the SPA calls `POST /api/v1/auth/refresh` (the cookie carries the refresh token; a custom request header is required) and retries once.

## Scope

- token issuer, JWT guard, JWKS endpoint
- API auth endpoints replacing Breeze web auth
- `GET /me` and `GET /meta` replacing Inertia shared props
- header-driven locale negotiation and coded validation errors
- standalone SPA in `apps/web` with vue-router, pinia, vue-i18n
- monorepo layout, nginx cutover, removal of Inertia, Ziggy, Breeze web routes
- OpenAPI contract and a contract test job in CI
- documentation updates

## Non-Goals

- the iOS app itself (it consumes the finished contract)
- the Go backend itself (it must pass the contract suite)
- Google / Apple login implementation (designed for, not built here)
- MFA, passkeys, SSO
- any product feature work

## Story Order

1. [OpenAPI Contract Skeleton](stories/01-openapi-contract-skeleton.md)
2. [Token Issuer and JWT Guard](stories/02-token-issuer-and-jwt-guard.md)
3. [API Auth Endpoints](stories/03-api-auth-endpoints.md)
4. [Me and Meta Endpoints](stories/04-me-and-meta-endpoints.md)
5. [Locale Negotiation and Error Codes](stories/05-locale-negotiation-and-error-codes.md)
6. [SPA Scaffold and Auth Store](stories/06-spa-scaffold-and-auth-store.md)
7. [SPA Page Migration](stories/07-spa-page-migration.md)
8. [SPA Auth Pages](stories/08-spa-auth-pages.md)
9. [Monorepo Layout and nginx Cutover](stories/09-monorepo-layout-and-nginx-cutover.md)
10. [Remove Inertia and Breeze](stories/10-remove-inertia-and-breeze.md)
11. [Contract Tests in CI](stories/11-contract-tests-in-ci.md)

## Sequencing Rationale

- The contract comes first so every later story has a target to code against.
- Backend stories (2-5) ship while the Inertia frontend keeps working, so the app stays deployable at every step.
- The SPA (6-8) is built against the finished API and runs in parallel with the Inertia app until cutover.
- Cutover and removal (9-10) happen only once the SPA covers every page.
- Contract tests (11) close the loop and become the acceptance gate for the Go backend.

## Success Metrics

- zero Inertia, Ziggy, or Breeze web references remain in the backend
- every client request is authenticated by a JWT the backend can verify without a session store
- the contract test job passes against the PHP API
- the SPA has no runtime dependency on the backend beyond `/api/v1`

## Risks

- custom token issuance is security-critical; mitigated by a small surface, tests for rotation and reuse detection, and review
- a long-lived branch drifts from `main`; mitigated by shipping stories 2-5 behind the existing Inertia frontend
- moving the Laravel app into a subfolder touches deploy scripts and CI; mitigated by doing it in a dedicated story with a full docker-compose run

## Done Criteria

This epic is done when:

- all eleven stories are merged or explicitly deferred
- the production stack runs the SPA container and the API container behind nginx
- `docs/` describes the API-only architecture
- the contract test job is green in CI

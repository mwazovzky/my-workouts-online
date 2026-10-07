# User Story: Shared Kernel, HTTP Platform and Meta

Build the dependency-free kernel (errors, i18n, validation, pagination, principal, clock, transactor interface), the generated HTTP server with its middleware chain, and ship `getMeta` as the first contract operation so the end-to-end test style is settled before any domain code.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Every domain module needs the same cross-cutting pieces: a typed error taxonomy that maps to RFC 9457 Problem Details, locale negotiation from `Accept-Language` with `Content-Language` and `Vary` on the way out, a validation builder that produces `errors: [{pointer, code, detail}]` with the enumerated field codes, request-id propagation, panic recovery, JSON 404/405 bodies, and rate limiting behind a port. In PHP these live in `app/Exceptions/Api/ApiErrorResponse.php`, `app/Http/Middleware/NegotiateApiLocale.php`, `app/Http/Middleware/LogContext.php` and the framework. Building them once, with the generated server from ADR 0003 wired through a composition root, lets each later story add only handlers, use cases and queries.

## User-Visible Outcome

None. The web SPA and iOS app behave identically; `GET /api/v1/meta` on the Go port returns the same body as PHP.

## Scope

- `internal/kernel/apperr`: `NotFound`, `Conflict{Code}`, `Validation{Errors []FieldError}`, `Unauthenticated{Code}`, `Forbidden{Code}`, `RateLimited{RetryAfter}`, `PreconditionFailed`, each carrying the stable `code` from the contract's error registry; helpers for `errors.Is`/`errors.As`.
- `internal/kernel/i18n`: `Locale` type, `Negotiate(acceptLanguage)` with `x/text/language` against the configured locales (fallback `en`), `Bundle.T(locale, key, args)`, embedded `locales/{en,ru}.json` ported from `services/api-php/lang/{en,ru}.json` (only the keys the backend uses: enum labels, validation messages, status labels) and `lang/{en,ru}/{auth,passwords,validation}.php`.
- `internal/kernel/validate`: a builder (`v.Required("email", …)`, `v.Email`, `v.MaxLen`, `v.In`, `v.Range`, `v.Nested("activities", i, func(v *Validator))`) that emits `apperr.Validation` with JSON Pointer paths and the `FieldErrorCode` enum (`required`, `invalid_format`, `invalid_value`, `too_short`, `too_long`, `out_of_range`, `not_unique`, `not_found`, `mismatch`), messages from the bundle.
- `internal/kernel/pagination`: `Page{Number, Size}` parsing with the `per_page` maximum, `Result[T]`, `Links` and `Meta` builders producing absolute URLs.
- `internal/kernel/auth`: `Principal{UserID, IsAdmin, AuthTime, Family}` plus context helpers; `kernel/clock` (`Clock`, `Real`, `Fake`); `kernel/txn` (`Transactor` interface implemented by story 2).
- `internal/platform/httpapi/gen`: generated types and strict server interface from the contract (committed, diff-checked); `go generate` directive and `oapi-codegen.yaml`.
- `internal/platform/httpapi/middleware`: request id (accept a valid UUID in `X-Request-Id`, else mint one; echo it on every response), request logging with the slog fields, recover (panic → Problem 500, never a stack in the body), locale (sets `Content-Language`, appends `Accept-Language` to `Vary`), JSON 404/405 wrapper around the mux (`not_found` body with the "Record not found." title; 405 with `Allow`), error mapper (`apperr` → Problem status, `type`, `title`, localized `detail`, `instance` = request id, `code`), `openapi3filter` request validation mapped to 422 pointer errors so shape mistakes never reach a handler, rate-limiter port with an in-memory fixed-window adapter (`Retry-After` and `X-RateLimit-*` headers; `API_RATE_LIMIT_PER_MINUTE=100000` effectively disables it in CI as it does for PHP).
- `internal/platform/httpapi/respond`: Problem writer (`application/problem+json`), JSON writer, `Cache-Control` helper.
- `internal/bootstrap`: `New(cfg) (*App, error)` returning the handler, pool, close hooks; `cmd/workouts serve` uses it, tests use it with a test config.
- First operation: `getMeta` returning `available_locales` and `theme_preferences` with `Cache-Control: public, max-age=3600`, mirroring `app/Http/Controllers/Api/MetaController.php`.
- HTTP test style, established here and reused everywhere: `httptest` against `bootstrap.New`, `openapi3filter` response validation against the contract, golden JSON files with an `-update` flag, a request helper that sets `Accept-Language` and a bearer token.

## Non-Goals

- authentication (story 4); the bearer middleware slot exists but passes through
- any database-backed operation
- OpenTelemetry (story 12); the middleware chain leaves a slot for `otelhttp`

## Acceptance Criteria

- `GET /api/v1/meta` on `:8081` returns the same JSON as PHP (checked with a golden file copied from the PHP response) and the cache header
- an unknown path returns a 404 Problem with `code: not_found`; a wrong method returns 405 with `Allow`
- a request with `Accept-Language: ru` gets `Content-Language: ru` and `Vary: Accept-Language`; `de` falls back to `en`
- a malformed JSON body on any operation returns a 422 Problem with `errors[0].pointer` and `code: invalid_format`
- a panicking handler returns a 500 Problem and logs the stack with the request id
- every response carries `X-Request-Id`
- `scripts/contract-test.sh http://localhost:8081` with `CONTRACT_INCLUDE=getMeta` is green (the script's login step is skipped when the include list contains only public operations; see story 4 for the full wiring)

## Initial Tasks

- [ ] kernel packages with unit tests (table-driven; `i18n` tested on every bundle key the backend uses)
- [ ] generated server, `go generate`, `make check` diff gate
- [ ] middleware chain and `respond`
- [ ] `bootstrap.New`, `serve` wiring
- [ ] `getMeta` handler, golden test, response validation helper
- [ ] contract script scoping groundwork

## Test Cases

- `apperr` → Problem mapping for every error type, including localized `detail` in `ru`
- `validate`: nested pointer paths (`/activities/0/sets/1/effort_value`), every field code reachable
- `pagination`: boundaries (`page=0`, `per_page` over the maximum, empty result links)
- rate limiter with a fake clock: window reset, `Retry-After` arithmetic
- locale negotiation: quality values, unsupported languages, missing header
- mirrors `tests/Feature/Api/{ErrorEnvelope,LocaleNegotiation,Meta,ValidationLocale}Test.php` and `tests/Feature/LogContextTest.php`

## Docs To Update

- `docs/architecture.md` (Go kernel and middleware chain; error mapping table)
- `services/api-go/README.md` (test style, golden files, `-update`)

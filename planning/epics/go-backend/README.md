# Epic: Go Backend

**Status: in progress (story 2); the Contract Hardening epic shipped 2026-10-10 with the contract at `2.0.0`.**

Build a second backend in Go under `services/api-go` that implements `contracts/openapi.yaml` next to the Laravel API, as a learning project in modern Go design: hexagonal modular monolith, sqlc on Postgres, generated HTTP layer from the contract, one `make check` gate. The PHP service stays the production default; the Go service is done when it passes the same Schemathesis contract suite with no scope filter.

## Why This Epic Exists

Nothing is wrong with the PHP backend. The motivation is learning: apply current industry practice for solution design, architecture and implementation in Go, on a domain that is already fully specified by a contract, a test suite and two clients. The API-First Decoupling epic made this possible: every client talks only to `/api/v1`, the contract is enforced in CI, and the contract tests were written to become "the acceptance gate for the Go backend". The iOS epic proved a second client could be built from the contract alone; this epic proves a second server can.

The [Contract Hardening](../contract-hardening/README.md) epic runs first. It fixes the Laravel idioms that leaked into the wire format (error envelope, path parameter names, PATCH semantics, enum casing) so the Go backend implements the improved contract once instead of reproducing quirks and then migrating.

## Decisions

Learning value and a codebase that stays pleasant to work with are the constraints. Every choice below prefers idiomatic, widely adopted Go over frameworks, and explicitness over magic. Each row gets a MADR record under `services/api-go/docs/adr/` when its story ships.

| # | Decision | Choice |
| --- | --- | --- |
| 1 | End state | Coexist. Both backends implement the contract and pass `scripts/contract-test.sh`; nginx selects one with `API_BACKEND=php\|go`, default `php`. A production rollout of Go is a later epic. |
| 2 | Location | `services/api-go/`, Go module `github.com/mwazovzky/my-workouts-online/services/api-go`, latest stable Go with the toolchain pinned in `go.mod`. |
| 3 | Database | Postgres 17 with a schema owned by Go migrations (`pressly/goose`, SQL files embedded, run by `workouts migrate`). No coupling to the Laravel MySQL schema; no shared database between backends. |
| 4 | Architecture | Hexagonal modular monolith. Modules `identity`, `catalog`, `training`, `dashboard`, each with `domain/` (types, invariants), `app/` (use cases), `ports/` (interfaces), `adapters/` (postgres, http, and jwt, bcrypt, links, catalogfile where needed) and an exported `<module>api/` package that is the only cross-module surface. A dependency-free `kernel/` (apperr, i18n, validate, pagination, auth principal, clock, transactor) and an infrastructure `platform/` (config, postgres, httpapi, observability, mail, jobs, testdb). One composition root in `bootstrap/`. `depguard` enforces the dependency table. No transaction spans two modules. |
| 5 | DB access | `sqlc` compiling hand-written SQL to typed Go over `pgx/v5` + `pgxpool`; one generated package per module from `sql/<module>/*.sql`. No ORM. |
| 6 | HTTP layer | `oapi-codegen` v2 strict server on the stdlib `net/http.ServeMux`, generated from the hardened contract, output committed and diff-checked in `make check`. Per-operation auth and throttle policy keyed by `operationId` from the embedded spec. Story 1 spikes the generator against the OpenAPI 3.1 constructs; fallbacks in order are a build-time 3.0 down-convert, then `ogen`. |
| 7 | Schema departures | No polymorphic `activities`: `template_activities`/`template_sets` in catalog and `workout_activities`/`workout_sets` in training with real foreign keys. No EAV `translations` table: English stays in `name`/`description`, other locales in a `translations jsonb` column; localized reads use `COALESCE(translations #>> ARRAY[$locale,'name'], name)`. `program_user` and `role_user` gain unique constraints. Recorded in [ADR 0004](../../../services/api-go/docs/adr/0004-translations-as-jsonb.md) and [ADR 0005](../../../services/api-go/docs/adr/0005-split-activity-tables.md). |
| 8 | Signed links | Go-native HMAC-SHA256 signature over `verify\|id\|hash\|expires` with `LINK_SIGNING_KEY`; clients only post the four fields back, so Laravel's `APP_KEY` URL signing is not reproduced. |
| 9 | Catalog data | Shared YAML at repo root `catalog/`, exported once from `services/api-php/database/catalog/*.php`. Go reads it from story 7; a PHP `catalog:export --check` step guards drift until story 14 switches PHP's `CatalogLoader` to the same files and deletes the PHP copies. |
| 10 | Errors and validation | `kernel/apperr` typed errors wrapped with `%w` and matched at the HTTP boundary only; a hand-written `kernel/validate` builder emitting RFC 9457 Problem Details with JSON Pointer field errors; `openapi3filter` request validation as the shape safety net. |
| 11 | Auth | `lestrrat-go/jwx/v3` for Ed25519 JWTs and the JWKS document, same claims and `kid` derivation as `TokenService.php`; `x/crypto/bcrypt` cost 12 (verifies Laravel `$2y$` hashes if users are ever imported); refresh-token families with rotation and reuse detection under `SELECT … FOR UPDATE`. |
| 12 | Jobs | River (Postgres-backed) for the inbound support-email job, enqueued in the same transaction as the webhook record; `workouts work` runs the workers in a `worker-go` container. |
| 13 | Observability | `log/slog` JSON to stderr with `request_id`; OpenTelemetry (`otelhttp`, `otelpgx`, runtime metrics) with a Prometheus exporter on a private admin port and an OTLP switch; nothing public. |
| 14 | Testing | stdlib `testing` + `go-cmp`; `testcontainers-go` Postgres with a template database per test (`TEST_DATABASE_URL` reuses the compose database); `httptest` against `bootstrap.New`; `openapi3filter` response validation; golden JSON files; hand-written fakes for ports, no mock generators. Each domain story mirrors the PHPUnit feature tests it replaces. |
| 15 | Quality gate | `make check` = gofumpt, vet, golangci-lint v2 (staticcheck, errorlint, revive, gocritic, exhaustive, gosec, depguard), generated-code diff, sqlc diff, `go test -race -shuffle=on` with a coverage threshold excluding generated code, govulncheck. CI runs exactly `make check`. |
| 16 | Delivery | Vertical slices. From story 4, `scripts/contract-test.sh http://localhost:8081` runs with `CONTRACT_INCLUDE` listing the operations covered so far (`services/api-go/contract-scope.txt`), deleted in story 13 so the full suite runs. |
| 17 | Image and compose | Multi-stage Dockerfile to `distroless/static`, non-root, `workouts healthcheck` for the container healthcheck; dev compose adds `postgres` and `api-go` on host port 8081; prod compose adds `postgres`, `api-go`, `worker-go` with memory limits. |
| 18 | CLI | One binary `workouts` with stdlib `flag` subcommands: `serve`, `work`, `migrate`, `seed`, `catalog sync`, `keys generate`, `user grant-admin`, `healthcheck`. |
| 19 | Standards documentation | Three layers with one job each: `.golangci.yml` (with `depguard`) enforces what can be checked mechanically; `services/api-go/docs/code-style.md` states every convention in prose, including the ones no linter can check (invariants on domain types, context carries only infrastructure values, hand-written fakes), and says per rule whether a linter or a reviewer enforces it; ADRs under `services/api-go/docs/adr/` record why. Every non-default lint rule carries a comment naming its standard section or ADR. Lands in story 1. |
| 20 | Nothing Go-specific before this epic starts | Until the Contract Hardening epic ships, the only Go-related artifact in the repository is this plan. No `services/api-go`, no Go documentation, no Go mentions in `docs/` or `CLAUDE.md`; the plan is adapted as the hardening stories land. |

## Target Architecture

```
contracts/openapi.yaml ──oapi-codegen──▶ services/api-go/internal/platform/httpapi/gen (committed)
catalog/*.yaml         ──catalogfile───▶ services/api-go/internal/catalog (sync, seed)

services/api-go/
  cmd/workouts/main.go        serve | work | migrate | seed | catalog sync | keys generate | user grant-admin | healthcheck
  migrations/                 goose SQL: identity, catalog, training, river (also sqlc's schema input)
  sql/{identity,catalog,training,dashboard}/*.sql   sqlc queries, one generated package per module
  internal/
    kernel/                   apperr, i18n, validate, pagination, auth, clock, txn — imports nothing internal
    platform/                 config, postgres, httpapi/{gen,middleware,respond}, observability, mail, jobs, testdb
    identity/                 users, roles, refresh tokens, verification, password reset, /me
    catalog/                  equipment, categories, exercises, templates, programs, enrollment, admin CRUD, sync
    training/                 workouts, workout activities, sets
    dashboard/                read model over catalogapi + trainingapi
    bootstrap/                composition root: New(cfg) -> *App{Handler, Pool, Workers, Close}
  docs/adr/                   one MADR record per decision above

Allowed imports: kernel <- platform <- module adapters; module app/ports <- own domain + other modules' *api only
  identity -> (none)   catalog -> (none)   training -> catalogapi   dashboard -> catalogapi, trainingapi

Request flow: nginx (API_BACKEND=go) -> proxy_pass :8080 -> otelhttp -> request id -> locale -> auth
  -> generated router -> strict handler -> use case -> ports -> sqlc/pgx -> Postgres
Errors: use case returns apperr -> http adapter maps to RFC 9457 Problem (application/problem+json)
```

## Scope

- `services/api-go` implementing every operation in the hardened contract, plus `/health`, `/health/ready`, `/up`, `/.well-known/jwks.json` and `POST /api/webhooks/resend`
- Postgres schema, migrations, seeding, catalog sync CLI, mail and the inbound-email worker
- CI jobs `go.yml` (`make check`) and `contract-go.yml` (Schemathesis), both path-filtered
- dev compose services, image build in `deploy.yml`, prod compose services, nginx `API_BACKEND` switch shipped with default `php`
- the shared YAML catalog and the PHP switch to it (story 14)
- ADRs, `services/api-go/README.md`, `CLAUDE.md` command block, `docs/` updated as each story ships

## Non-Goals

- switching production to Go, importing MySQL data into Postgres, or retiring the PHP service
- any change to `contracts/openapi.yaml` beyond what the Contract Hardening epic already shipped
- social login (`socialLogin` stays `x-status: target`), or any product feature the PHP backend does not have
- changes to the web SPA or the iOS app
- multi-instance concerns (distributed rate limiting, shared caches)

## Story Order

| # | Story | Primary feature | State |
| --- | --- | --- | --- |
| 1 | [Scaffold, Toolchain, CI and Image](stories/01-scaffold-toolchain-ci-and-image.md) | Platform | shipped |
| 2 | [Schema, Migrations, sqlc and Test Harness](stories/02-schema-migrations-sqlc-and-test-harness.md) | Platform | in progress |
| 3 | [Shared Kernel, HTTP Platform and Meta](stories/03-shared-kernel-http-platform-and-meta.md) | Platform | planned |
| 4 | [Identity: Tokens, Login, Refresh and JWKS](stories/04-identity-tokens-login-refresh-and-jwks.md) | Auth & Profile | planned |
| 5 | [Identity: Verification, Reset and Me](stories/05-identity-verification-reset-and-me.md) | Auth & Profile | planned |
| 6 | [Catalog Read and Enrollment](stories/06-catalog-read-and-enrollment.md) | Programs | planned |
| 7 | [Shared YAML Catalog and Go Sync CLI](stories/07-shared-yaml-catalog-and-go-sync-cli.md) | Platform | planned |
| 8 | [Workouts](stories/08-workouts.md) | Workout Logging | planned |
| 9 | [Dashboard](stories/09-dashboard.md) | Workout Logging | planned |
| 10 | [Admin Catalog CRUD](stories/10-admin-catalog-crud.md) | Admin Content Management | planned |
| 11 | [Mail, Inbound Webhook and Worker](stories/11-mail-inbound-webhook-and-worker.md) | Platform | planned |
| 12 | [Observability](stories/12-observability.md) | Platform | planned |
| 13 | [Deployment Plumbing and Backend Switch](stories/13-deployment-plumbing-and-backend-switch.md) | Platform | planned |
| 14 | [PHP Reads the Shared YAML Catalog](stories/14-php-reads-shared-yaml-catalog.md) | Platform | planned |

States: `planned`, `in progress`, `shipped`, `deferred`. Update the row when a story's PR opens and when it merges; archive or delete the story file after it ships, per `planning/README.md`.

## Sequencing Rationale

- Stories 1 to 3 are platform only and land the gate (`make check`, CI, image, kernel, middleware) before any domain code, so every later PR is checked from day one. Story 1 also measures the generator against the 3.1 contract before anything depends on it.
- Stories 4 and 5 unlock Schemathesis: `scripts/contract-test.sh` logs in first, so no contract run is possible before login works.
- Story 6 (catalog reads) precedes story 7 (sync CLI) so the sync tests assert through the same repositories the API uses.
- Story 8 (workouts) is the core value and needs `catalogapi` from story 6; story 9 (dashboard) needs both.
- Story 10 (admin) is the long tail of 25 similar operations and comes after the read side it mirrors.
- Stories 11 to 13 are infrastructure: mail and jobs, observability, then deployment plumbing that closes the scope filter and runs the full suite.
- Story 14 changes PHP last, so the Go sync is proven before the PHP production path changes.

## Success Metrics

- `scripts/contract-test.sh http://localhost:8081` is green with no `CONTRACT_INCLUDE` filter, and `contract-go` is a required check on `main`
- `make check` is green on every PR; coverage threshold met excluding generated code
- zero hand-written request or response structs: every operation goes through the generated strict server
- a contract change that removes or renames a field fails `go.yml` at compile time, as it does for iOS
- `docker compose up` with `API_BACKEND=go` serves the web SPA end to end against Go, including register, verify, enroll, log and complete a workout
- the PHP contract job, web tests and iOS job are unaffected by every story except 7 and 14

## Risks

- `oapi-codegen` and OpenAPI 3.1: the contract uses `type: [X, 'null']` widely and `allOf` in several schemas. Mitigated by measuring in story 1 before anything depends on it; the contract-hardening epic removes the generic `Paginated` `allOf`; fallbacks are a build-time 3.0 down-convert, then `ogen`. Spec edits stay wire-identical and are validated against redocly, the Swift generator and the PHP contract job on the same PR.
- Schemathesis checks shape, not semantics: dashboard date maths, lockout counts, 409 guards and the negative temporary `order` trick are invisible to it. Mitigated by porting the relevant PHPUnit feature tests case by case; each domain story names the files it mirrors.
- Droplet memory: `postgres`, `api-go` and `worker-go` add roughly 250 to 350 MB. Mitigated by memory limits in story 13 and by deferring the rollout to a later epic.
- Sessions across a backend switch: separate databases mean a PHP-issued refresh cookie is invalid on Go, forcing a sign-in. Accepted; sharing the JWT key pair keeps access tokens valid for their 15 minutes.
- Catalog drift between stories 7 and 14 while PHP still reads `.php` files. Mitigated by the `catalog:export --check` CI step.
- Two catalog validators (PHP and Go) until story 14, and forever if PHP keeps its own. Mitigated by shared invalid fixtures under `catalog/testdata/invalid/` with expected rule ids consumed by both suites.
- A second backend for a solo maintainer. Mitigated by the contract as the single source of truth, generated code wherever the contract allows, and by keeping behaviour identical to PHP rather than redesigning it.

## Done Criteria

This epic is done when:

- all fourteen stories are shipped or explicitly deferred
- the full Schemathesis suite is green against Go and `contract-go` is required on `main`
- `docker compose up` with `API_BACKEND=go` serves the SPA end to end and production compose can start the Go services with `API_BACKEND=php` still the default
- `catalog/*.yaml` is the only catalog source and both backends read it
- `docs/` describes the Go service as current state (architecture, deployment, testing) and every decision above has an ADR

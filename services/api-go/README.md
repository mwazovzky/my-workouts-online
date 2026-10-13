# Workouts API (Go)

A second backend for My Workouts, implementing the same contract as the Laravel API (`contracts/openapi.yaml`) as a hexagonal modular monolith in Go. It is built story by story in [the Go backend epic](../../planning/epics/go-backend/README.md); today it answers the health probes, `GET /api/v1/meta`, every identity operation (register, login, refresh, logout, reauth, JWKS, email verification, password reset, `/me`) the catalog's read side (programs, program detail, enrollment, workout templates, the exercise picker) every workout operation (history, start, the editor's save, complete, repeat, delete), the dashboard and the 25 admin catalog operations, sends mail through Resend, accepts Resend's support-inbox webhook and forwards the email from a River worker, owns its Postgres schema, and answers only social login (`x-status: target` in the contract) with a 501 Problem. The PHP API stays the production backend.

## Run

The dev stack runs it in Docker; Go on the host is optional.

```bash
docker compose up -d api-go          # starts postgres too; http://localhost:8081
curl -i localhost:8081/health        # {"status":"ok","timestamp":"…"}
curl -i localhost:8081/health/ready  # 200 with components.database up, or 503 with status degraded
curl -i localhost:8081/api/v1/meta -H 'Accept-Language: ru'  # the same body as the PHP API
docker compose restart api-go        # pick up code changes (the dev container builds, migrates, then serves)
docker compose logs -f api-go        # JSON logs
docker compose up -d worker-go       # the job worker (`workouts work`); restart it after code changes too
```

With Go 1.27 on the host: `DATABASE_URL=postgres://workouts:secret@localhost:5432/workouts?sslmode=disable make run` (run `go run ./cmd/workouts migrate up` with the same URL first).

## Commands

The binary is `workouts` (`cmd/workouts`):

| Command | Does |
| --- | --- |
| `workouts serve` | Opens the database pool (failing fast when it is unreachable), then runs the HTTP API on `HTTP_ADDR` until SIGINT or SIGTERM, then drains (see Shutdown). |
| `workouts work` | Runs the background job workers (today: forwarding support email) until SIGINT or SIGTERM; jobs still running then get `SHUTDOWN_TIMEOUT` to finish before they are cancelled and retried later. The dev stack runs it as `worker-go`. |
| `workouts migrate up` | Applies the pending migrations; running it again is a no-op. A deploy runs it before starting the new version. |
| `workouts migrate status` | Lists every migration with its state and when it was applied. |
| `workouts seed` | Creates (or resets) the verified demo admin `test@example.com` / `password`, then applies the catalog (as `catalog sync`). Refused when `APP_ENV` is `production` or unset. |
| `workouts catalog sync [--dry-run] [--strict]` | Applies the YAML catalog in `CATALOG_DIR`, as `php artisan catalog:sync`: prints counts per type and outcome, the changes and the warnings. `--dry-run` writes nothing; `--strict` writes nothing when there are warnings and exits 1. An unreadable or invalid catalog lists what is wrong and exits 1. |
| `workouts user grant-admin <email>` | Gives a registered, verified user the Admin role, as `php artisan user:grant-admin`. |
| `workouts keys generate` | Prints a new `JWT_PRIVATE_KEY` / `JWT_PUBLIC_KEY` pair, in the format `php artisan jwt:keys` prints. |
| `workouts healthcheck` | Exits 0 when `GET /health` on the local port answers 200. The image's `HEALTHCHECK`; distroless has no shell or curl. |

## Develop

Run every task in `services/api-go`, or through the container: `docker compose exec -T api-go make <target>`.

| Target | Does |
| --- | --- |
| `make check` | The gate CI runs: `go mod tidy -diff`, `golangci-lint fmt --diff`, `go vet`, `golangci-lint run`, regenerate and fail on a diff, `go test -race -shuffle=on` with the coverage threshold, `govulncheck`. Run before every commit. |
| `make fmt` | Formats with gofumpt and goimports. |
| `make generate` | Regenerates `internal/platform/httpapi/gen/gen.go` from `contracts/openapi.yaml` and the `sqlcgen` packages from `sql/` and `migrations/`. Commit the result. |
| `make test` | Tests with the race detector and the coverage threshold (`COVERAGE_MIN`, generated code excluded). |
| `make run` | `go run ./cmd/workouts serve`. |

golangci-lint and sqlc are downloaded into `bin/` at the versions pinned in the `Makefile`; oapi-codegen and govulncheck are `tool` directives in `go.mod` (`go tool …`).

### Tests

- **HTTP tests** go through the real handler from `bootstrap.New` with `internal/platform/httpapi/apitest`. `Do` sends a request with a fixed `X-Request-Id`, plus `Accept-Language` and a bearer token when set. It validates every response to a contract operation against `contracts/openapi.yaml` and fails the test on a mismatch.
- **Golden files** live in the package's `testdata/` and are compared as JSON values, so a body copied from the PHP API is a valid golden file (`internal/bootstrap/testdata/meta.golden.json` is). `go test ./internal/bootstrap -run TestName -update` rewrites the golden files of the tests it runs. Review the diff, and never `-update` a file copied from PHP.
- **Coverage** is measured across packages (`-coverpkg=./...`), generated code excluded.
- **Contract tests:** Schemathesis runs against the Go API for the operations in `contract-scope.txt`; the `contract-go` workflow does it on every PR that touches the service or the contract. Locally, seed first (`docker compose exec -T api-go go run ./cmd/workouts seed`), then run `CONTRACT_INCLUDE=$(grep -v '^#' services/api-go/contract-scope.txt | paste -sd, -) scripts/contract-test.sh http://localhost:8081` from the repository root. Add an operation to the scope file in the story that implements it.

## Database

Postgres 17, a schema of its own (nothing is shared with the PHP API's MySQL database). Tables are owned by modules: identity (`users`, `roles`, `role_user`, `refresh_tokens`, `password_reset_tokens`), catalog (`equipment`, `categories`, `exercises`, `category_exercise`, `workout_templates`, `template_activities`, `template_sets`, `programs`, `program_workout_template`, `program_user`), training (`workouts`, `workout_activities`, `workout_sets`), inbox (`inbound_emails`), plus River's job tables. Two departures from the Laravel schema are recorded in [ADR 0004](docs/adr/0004-translations-as-jsonb.md) (translations in a `jsonb` column) and [ADR 0005](docs/adr/0005-split-activity-tables.md) (template and workout activities in separate tables).

- **Migrations** are goose SQL files in `migrations/`, embedded in the binary, plus Go migration 00004, which installs River's schema through River's own migrator. Add one with the next number and a `-- +goose Down` section; never edit an applied one.
- **Queries** are hand-written SQL in `sql/<module>/*.sql`. `make generate` compiles them with sqlc against the migrations into `internal/<module>/adapters/postgres/sqlcgen`. A module gets its `sqlc.yaml` block with its first query.
- **Transactions**: a use case runs a unit of work through `kernel/txn.Transactor`; `platform/postgres.Transactor` carries the `pgx.Tx` in the context, and repositories call `Querier(ctx)` so they join it. `postgres.MapError` turns no rows into `apperr` NotFound and unique or foreign-key violations into Conflict.

Inspect the dev database: `docker compose exec postgres psql -U workouts workouts`.

### Test databases

Each test that needs Postgres calls `testdb.New(t)` and gets a fresh database cloned from a migrated template (`CREATE DATABASE … TEMPLATE`, milliseconds), dropped when the test ends, so database tests run with `t.Parallel()`. The package declares `func TestMain(m *testing.M) { testdb.Main(m) }`.

| `TEST_DATABASE_URL` | Server the test databases live on |
| --- | --- |
| set (the `api-go` container sets it to the compose `postgres`) | that server; the URL must name a role allowed to create databases |
| unset (CI, or Go on the host) | a `postgres:17` container per test package, started by testcontainers; needs Docker |

## Configuration

Environment variables, read by `internal/platform/config`. Names shared with the PHP API keep their PHP spelling, so one `.env` serves both.

| Variable | Default | Meaning |
| --- | --- | --- |
| `DATABASE_URL` | required | Postgres URL. Compose builds it from `DB_USERNAME`, `DB_PASSWORD` and `DB_DATABASE`. Pool settings go in the query string (`pool_max_conns=10`). |
| `HTTP_ADDR` | `:8081` | Listen address. |
| `APP_URL` | `http://localhost:8080` | Public base URL; a Problem's `type` is `{APP_URL}/problems/{code}`. |
| `API_RATE_LIMIT_PER_MINUTE` | `60` | Requests per minute per client IP under `/api/`; over it, 429 with `Retry-After`. CI sets `100000`. |
| `LOG_LEVEL` | `info` | `debug`, `info`, `warn`, `error`, or Laravel's names (`notice`, `warning`, `critical`, …). |
| `SHUTDOWN_DRAIN_DELAY` | `2s` | How long `/health/ready` answers 503 before the listener closes. |
| `SHUTDOWN_TIMEOUT` | `5s` | How long in-flight requests may take to finish after that. |
| `APP_ENV` | `production` | As in Laravel; `seed` refuses to run in production. |
| `CATALOG_DIR` | `catalog` | The shared YAML catalog `catalog sync` and `seed` apply: `/src/catalog` in the dev container, `/catalog` in the image, the repository's `catalog/` from its root. |
| `APP_NAME` | `My Workouts` | Signs off the emails. |
| `FRONTEND_URL` | `APP_URL` | The SPA; verification and reset links in emails point at it. |
| `LINK_SIGNING_KEY` | dev key outside production | HMAC key for verification links (ADR 0008); at least 32 characters, required in production. |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost. |
| `JWT_PRIVATE_KEY`, `JWT_PUBLIC_KEY` | none | Base64 raw Ed25519 keys (`workouts keys generate` or `php artisan jwt:keys`). Without them the API serves public routes and readiness reports `jwt: down`; malformed keys stop it at startup. The dev stack shares PHP's pair from `.env`, so tokens verify on both backends. |
| `JWT_KEY_ID` | derived | `kid` override; otherwise the first 16 hex characters of the public key's SHA-256, as PHP derives it. |
| `JWT_ISSUER`, `JWT_AUDIENCE` | `APP_URL`, `my-workouts` | Checked on every access token. |
| `JWT_ACCESS_TTL`, `JWT_REFRESH_TTL`, `JWT_LEEWAY` | `900`, `2592000`, `30` | Seconds. |
| `JWT_COOKIE_SECURE` | `true` | `Secure` on the refresh cookie; `false` only for plain-HTTP dev and CI. |
| `MAIL_MAILER` | `log` | `log` writes each message to the log; `resend` sends through Resend. Anything else stops start-up. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `hello@example.com`, `APP_NAME` | The sender of every email. |
| `MAIL_INBOUND_FORWARD_TO` | none | Where support email is forwarded; blank forwards nothing (the worker logs a warning). |
| `RESEND_KEY` | none | The Resend API key; required with `MAIL_MAILER=resend`, and used by the worker to read received email. |
| `RESEND_WEBHOOK_SECRET` | none | The webhook signing secret (`whsec_…`); blank rejects every webhook with 403, a malformed one stops start-up. |
| `RESEND_BASE_URL` | `https://api.resend.com/` | The Resend API root; tests point it at a fake server. |

## HTTP Platform

Every request passes the same chain (`internal/platform/httpapi`):

1. **Request id:** a UUID from `X-Request-Id` is kept, anything else is replaced, and the id is echoed on every response and logged.
2. **Access log:** one JSON line per request: `request_id`, `method`, `path`, `route`, `status`, `bytes`, `duration_ms`.
3. **Recover:** a panic becomes a 500 Problem; the stack goes to the log, never to the body.
4. **Locale:** from `Accept-Language` (`en`, `ru`; anything else is `en`), answered with `Content-Language` and `Vary: Accept-Language`.
5. **Rate limit** (see `API_RATE_LIMIT_PER_MINUTE`).
6. **Routing:** an unknown path is a 404 Problem, a wrong method a 405 Problem with `Allow`, and a non-numeric id in a path a 404.
7. **Policy:** inside each contract operation, operations with `security: []` are public; any other needs a valid bearer token (401 `unauthenticated` or `token_expired`), a verified email unless tagged `Auth` or `Me` (403 `email_unverified`), and `Admin`-tagged ones an admin (403 `forbidden`). Per-operation throttles follow (register 10/min; reauth, verify, resend, forgot and reset 6/min; 429).
8. **Request validation:** the request is checked against the contract, and failures are a 422 with one `{pointer, code, detail}` per field. Malformed JSON is one `invalid_format` error with the empty pointer.
9. **Generated strict server:** the module handler runs, and a returned error goes through the responder.

Errors are `kernel/apperr` values, which only the responder turns into Problems:

| `apperr` kind | Status | Codes |
| --- | --- | --- |
| NotFound | 404 | `not_found` (detail always "Record not found.") |
| Conflict | 409 | `conflict`, `in_use` |
| Validation | 422 | `validation_failed`, with `errors` |
| Unauthenticated | 401 | `unauthenticated`, `token_expired`, `invalid_credentials`, `refresh_token_*` |
| Forbidden | 403 | `forbidden`, `email_unverified` |
| RateLimited | 429 + `Retry-After` | `too_many_requests`, `too_many_attempts` |
| PreconditionFailed | 412 | `precondition_failed` |
| PreconditionRequired | 428 | `precondition_required` |
| BadRequest | 400 | `bad_request` |
| `httpapi.ErrNotImplemented` | 501 | `internal_error` |
| anything else | 500 | `internal_error`, detail "Server Error"; the error is logged |

Messages are English keys translated from `internal/kernel/i18n/locales/{en,ru}.json` (ported from the PHP lang files). Where the Go API deliberately answers differently from PHP is listed in [ADR 0006](docs/adr/0006-http-errors-locale-and-request-validation.md).

**Adding an operation:** implement its method on the module's handler type, embed that type in `internal/bootstrap/api.go`, and delete the operation's stub from `internal/bootstrap/unimplemented.go`. The build fails while both exist. A module's handler type is named after the module (`identityhttp.Identity`, `cataloghttp.Catalog`), because embedded fields are named after their type and two `Handlers` would collide.

## Catalog

The catalog module serves `listPrograms`, `getProgram`, `enrollProgram`, `unenrollProgram`, `getWorkoutTemplate` and `listExercises`, all for verified users. Repositories return each row's raw `translations` as `domain.LocalizedText`, and the request's locale is resolved in Go, falling back to English for a missing or blank translation ([ADR 0004](docs/adr/0004-translations-as-jsonb.md)). Related rows load with one query per relation: a template with any number of activities takes five queries, the exercise list two, and a test counts them.

Other modules read the catalog only through `internal/catalog/catalogapi`: `ExerciseLookup` (retired exercises included), `TemplateSnapshot` (a template to copy into a workout) and `EnrolledSchedule` (the dashboard's input). These are plain, already localized DTOs; `catalogapi.Fake` stands in for them in other modules' tests.

**Catalog sync.** The shipped catalog is the repository's [`catalog/`](../../catalog/README.md) YAML, which the PHP API mirrors until story 14 ([ADR 0009](docs/adr/0009-single-yaml-catalog-source.md)). `adapters/catalogfile` loads it (unknown keys are errors); `app/catalogsync` validates it against every rule of PHP's `CatalogValidator` (each with a stable id and a failing fixture in `catalog/testdata/invalid/<rule>/`) and applies it in one transaction with PHP's algorithm: match by slug, adopt an unmanaged row by English name or a previous name, reset managed rows to the files, leave admin-created rows alone, retire what `retired.yaml` lists unless it is still used. The Go counterparts of PHP's `CatalogSyncTest` scenarios assert the same counts, change lines and warnings.

The HTTP tests compare every response, in English and Russian, with golden files captured from the PHP API on the same catalog; `internal/catalog/adapters/http/testdata/README.md` explains how to recapture them. Where Go answers differently from PHP:

- An exercise's categories (`categories`, `category_ids`, `category_names`) are listed by category id. PHP lists them in pivot-row order, which the Go schema does not keep (`category_exercise` has no row id).
- Program lists are ordered by id and a program's templates by assignment. PHP relies on the database's unspecified order for the list; both give the same order in practice.

## Workouts

The training module serves `listWorkouts`, `createWorkout`, `getWorkout`, `replaceWorkoutActivities`, `completeWorkout`, `repeatWorkout` and `deleteWorkout`. A workout copies its template when it starts (name in the request's locale, kept as is), so it never reads the template tables again; it reads exercises and template headers through `catalogapi` only. Someone else's workout is a 404 on every operation, exactly like a missing one.

- **ETag.** Every single-workout response carries `ETag: "<id>-<version>"`, as PHP's. The editor's save needs `If-Match` (428 without it, 412 when stale, checked under `SELECT … FOR UPDATE`); the save and complete bump the version.
- **The save** replaces the activities and sets in one transaction: rows not sent are deleted, rows with an id updated, rows without one created. Kept rows first move to `order = -id`, so reordering never trips the `(parent, order)` unique keys. Its business rules and the departures from PHP (a retired exercise cannot be added; per-id error pointers; repeated set orders are a 422, not a 500) are in [ADR 0010](docs/adr/0010-retired-exercises-rejected-on-write.md).
- **Responses** show what PHP's show: `getWorkout` and the save include the activities with their exercises (retired ones resolve); `createWorkout` embeds the template it copied, with its activities and sets but not the workout's own; `complete` and `repeat` return the workout alone; the list adds `activities_count` and the template's header (left out once the template is deleted). Pagination links are absolute, built from the request's scheme, host and query.
- **`trainingapi`** (`RecentWorkouts`, `InProgress`, `Counts`) is what the dashboard (story 9) reads; `trainingapi.Fake` stands in for it in tests.

The tests port `tests/Feature/Workout/*`, `WorkoutApiTest` and `WorkoutAuthorizationTest` case by case, with golden files for one response of each shape, and a scripted flow run against both backends gave identical responses (ids, timestamps and the number of stored workouts aside).

## Admin catalog

The 25 `admin*` operations (equipment, categories, exercises, workout templates, programs) live in `internal/catalog/app/admin`; the `Admin` tag in the contract makes every one admin-only (403 for anyone else). Translations follow PHP: English is required (`An English name is required.`), values are trimmed, a blank value removes that locale's translation, and a locale that is not sent is left alone. Equipment cannot be deleted while any exercise uses it (retired ones included) and a template while a program schedules it (409 `in_use`); deleting an exercise retires it; categories and programs are deleted with their links. Records created here have no slug, so catalog sync leaves them alone. Every response shows what PHP's shows for that endpoint (lists with counts, single records with nested rows); a scripted flow against both backends and the admin lists and records of the real catalog matched. Guards and departures: [ADR 0012](docs/adr/0012-admin-delete-guards.md).

## Dashboard

`GET /dashboard` is served by `internal/dashboard`, a read model that owns no tables and writes nothing: it composes `catalogapi` (the enrolled schedule, the enrolled-programs count, template headers) and `trainingapi` (the in-progress workout, the 5 most recent, the completion counts). The schedule's dates follow PHP's arithmetic exactly: the next occurrence of each weekday from today (today included) at UTC midnight, ids `"{program_id}-{workout_template_id}-{date}"`, sorted by date with same-day items in assignment order. `in_progress_workout` is left out when there is none. Why no cross-table SQL: [ADR 0011](docs/adr/0011-dashboard-read-model.md).

## Mail

Identity's emails (verify email, reset password) are rendered from `internal/identity/adapters/notify/templates` in the request's locale and sent through `kernel/mail.Mailer`, synchronously as in PHP. `MAIL_MAILER` picks the sender: `log` (the dev stack and CI; `docker compose logs api-go | grep '"msg":"mail"'` shows each message with its link) or `resend` (`internal/platform/resend`). The links point at `FRONTEND_URL`.

## Support inbox and jobs

Resend posts `email.received` events to `POST /api/webhooks/resend` (off the contract, no bearer token). The `inbox` module checks the Svix signature against `RESEND_WEBHOOK_SECRET` and answers 403 Problems with PHP's wording otherwise. For a new email id it records the id in `inbound_emails` and inserts a River job in the same transaction, so a redelivered webhook queues nothing; ids are forgotten after a week. Every signed request is answered 204.

`workouts work` runs the job. It fetches the email and its attachments from Resend's receiving API and sends it to `MAIL_INBOUND_FORWARD_TO`:

- **Sender:** the app's address, named "<sender> via `APP_NAME`".
- **Reply-To:** the original Reply-To, or else the sender.
- **Body:** a summary of the original headers above the original body.

Jobs get 5 attempts, with retries 60, 300, then 900 seconds apart; an email Resend does not know (404) cancels the job. River's client lives in `internal/platform/jobs` (insert-only in `serve`). Inspect jobs with `docker compose exec postgres psql -U workouts workouts -c 'SELECT id, kind, state, attempt, errors FROM river_job ORDER BY id DESC LIMIT 5'`. Decisions and departures from PHP: [ADR 0013](docs/adr/0013-support-inbox-and-jobs.md).

## Shutdown

On SIGINT or SIGTERM `serve` flips `/health/ready` to 503, waits `SHUTDOWN_DRAIN_DELAY` so a proxy stops routing to it, stops accepting connections, lets in-flight requests finish within `SHUTDOWN_TIMEOUT`, closes resources in reverse order (the database pool last), and exits 0. A second signal kills the process. The defaults finish within Docker's 10-second stop grace period.

## Layout

```
cmd/workouts/                 the binary: subcommand dispatch, logger, signal handling
migrations/                   goose migrations (SQL embedded, River as Go migration 00004); sqlc's schema
sql/<module>/                 sqlc queries, one directory per module
sqlc.yaml                     sqlc configuration
internal/kernel/apperr/       typed application errors with the contract's error and field codes
internal/kernel/auth/         Principal (the authenticated caller) in the context
internal/kernel/clock/        Clock interface, System and Fake
internal/kernel/i18n/         locale negotiation and the embedded message catalogs
internal/kernel/pagination/   page parsing, PageMeta and absolute PageLinks
internal/kernel/txn/          Transactor port: InTx(ctx, fn)
internal/kernel/validate/     validation builder producing pointer field errors
internal/platform/config/     environment configuration
internal/platform/health/     getHealth, getReadiness, /up
internal/platform/httpapi/    middleware chain, request validation, Problem responder, routing
internal/platform/httpapi/apitest  HTTP test client: contract response validation, golden files
internal/platform/meta/       getMeta
internal/platform/ratelimit/  rate limiter and failure counter (login lockout), in-memory fixed windows
internal/platform/postgres/   pool, migrator, Transactor, pgx error mapper, readiness check
internal/platform/server/     http.Server with graceful shutdown
internal/platform/testdb/     a migrated database per test (template clone)
internal/platform/httpapi/gen generated from the contract by oapi-codegen (never edit gen.go); overlay.yaml holds Go-only generator hints
internal/identity/             users, roles, tokens, verification, reset, /me: domain/, ports/, app/, adapters/{postgres,bcrypt,jwt,links,notify,http}
internal/catalog/              programs, enrollment, templates, exercises, catalog sync, admin: domain/, ports/, app/ (+ app/catalogsync, app/admin), catalogapi/, adapters/{postgres,http,catalogfile}
internal/training/             workouts, the editor's save, complete, repeat: domain/, ports/, app/, trainingapi/, adapters/{postgres,http}
internal/dashboard/            the dashboard read model: app/ (use case, schedule dates), adapters/http
internal/inbox/                the support inbox: domain/ (the forwarded message), ports/, app/, adapters/{http,postgres,resend,jobs}
internal/kernel/mail/         the Mailer port
internal/platform/mail/       Log and Fake mailers
internal/platform/resend/     Resend API client (send, received email, attachments); resendtest/ is a fake server
internal/platform/jobs/       River client and the worker run loop
internal/identity/adapters/postgres/sqlcgen  generated by sqlc from sql/identity (never edit)
internal/catalog/adapters/postgres/sqlcgen   generated by sqlc from sql/catalog (never edit)
internal/training/adapters/postgres/sqlcgen  generated by sqlc from sql/training (never edit)
internal/inbox/adapters/postgres/sqlcgen     generated by sqlc from sql/inbox (never edit)
contract-scope.txt            the operations the contract-go workflow tests (deleted in story 13)
internal/bootstrap/           composition root: New(deps) -> *App; api.go assembles the strict server
docs/adr/                     architecture decision records
scripts/coverage-check.sh     coverage threshold without generated code
```

The module layout and the dependency rules `depguard` enforces are in [ADR 0001](docs/adr/0001-repository-layout.md) and the [code style](docs/code-style.md), which states every convention and who enforces it.

## Decisions

- [0000 Template](docs/adr/0000-template.md)
- [0001 Repository layout: a hexagonal modular monolith](docs/adr/0001-repository-layout.md)
- [0002 Library and tool choices](docs/adr/0002-library-choices.md)
- [0003 Generate the HTTP layer with oapi-codegen from the 3.1 contract](docs/adr/0003-openapi-code-generation.md)
- [0004 Store translations in a jsonb column per row](docs/adr/0004-translations-as-jsonb.md)
- [0005 Split template and workout activities into their own tables](docs/adr/0005-split-activity-tables.md)
- [0006 Problem Details, locale and request validation in the HTTP platform](docs/adr/0006-http-errors-locale-and-request-validation.md)
- [0007 Token compatibility with the PHP API, and auth policy from the contract](docs/adr/0007-identity-tokens-and-auth-policy.md)
- [0008 Go-native signed links, mail behind a port, and the verified gate](docs/adr/0008-go-native-signed-links.md)
- [0009 One YAML catalog for both backends, and its Go sync](docs/adr/0009-single-yaml-catalog-source.md)
- [0010 Workout writes: retired exercises rejected, and other departures from PHP](docs/adr/0010-retired-exercises-rejected-on-write.md)
- [0011 The dashboard is a read model over the modules' api packages](docs/adr/0011-dashboard-read-model.md)
- [0012 Admin catalog writes: delete guards and departures from PHP](docs/adr/0012-admin-delete-guards.md)
- [0013 Support inbox: Resend client, webhook idempotency and River jobs](docs/adr/0013-support-inbox-and-jobs.md)

## Image

`docker/api-go/Dockerfile`: the `dev` stage is the Go toolchain used by compose; the `api` stage is `distroless/static-debian13:nonroot` with the static binary at `/workouts`, `USER nonroot`, and `HEALTHCHECK` running `workouts healthcheck`.

```bash
docker build -f docker/api-go/Dockerfile --target api --build-arg VERSION=dev -t workouts-api-go .
```

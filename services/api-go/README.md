# Workouts API (Go)

A second backend for My Workouts, implementing the same contract as the Laravel API (`contracts/openapi.yaml`) as a hexagonal modular monolith in Go. It is built story by story in [the Go backend epic](../../planning/epics/go-backend/README.md); today it answers only the health probes and owns its Postgres schema. The PHP API stays the production backend.

## Run

The dev stack runs it in Docker; Go on the host is optional.

```bash
docker compose up -d api-go          # starts postgres too; http://localhost:8081
curl -i localhost:8081/health        # {"status":"ok","timestamp":"…"}
curl -i localhost:8081/health/ready  # 200 with components.database up, or 503 with status degraded
docker compose restart api-go        # pick up code changes (the dev container builds, migrates, then serves)
docker compose logs -f api-go        # JSON logs
```

With Go 1.27 on the host: `DATABASE_URL=postgres://workouts:secret@localhost:5432/workouts?sslmode=disable make run` (run `go run ./cmd/workouts migrate up` with the same URL first).

## Commands

The binary is `workouts` (`cmd/workouts`):

| Command | Does |
| --- | --- |
| `workouts serve` | Opens the database pool (failing fast when it is unreachable), then runs the HTTP API on `HTTP_ADDR` until SIGINT or SIGTERM, then drains (see Shutdown). |
| `workouts migrate up` | Applies the pending migrations; running it again is a no-op. A deploy runs it before starting the new version. |
| `workouts migrate status` | Lists every migration with its state and when it was applied. |
| `workouts healthcheck` | Exits 0 when `GET /health` on the local port answers 200. The image's `HEALTHCHECK`; distroless has no shell or curl. |

Later stories add `seed`, `work`, `catalog sync`, `keys generate` and `user grant-admin`.

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

Golden files: `go test ./internal/platform/health -update` rewrites `testdata/*.golden.json`; review the diff before committing.

## Database

Postgres 17, a schema of its own (nothing is shared with the PHP API's MySQL database). Tables are owned by modules: identity (`users`, `roles`, `role_user`, `refresh_tokens`, `password_reset_tokens`), catalog (`equipment`, `categories`, `exercises`, `category_exercise`, `workout_templates`, `template_activities`, `template_sets`, `programs`, `program_workout_template`, `program_user`), training (`workouts`, `workout_activities`, `workout_sets`), plus River's job tables. Two departures from the Laravel schema are recorded in [ADR 0004](docs/adr/0004-translations-as-jsonb.md) (translations in a `jsonb` column) and [ADR 0005](docs/adr/0005-split-activity-tables.md) (template and workout activities in separate tables).

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
| `LOG_LEVEL` | `info` | `debug`, `info`, `warn`, `error`, or Laravel's names (`notice`, `warning`, `critical`, …). |
| `SHUTDOWN_DRAIN_DELAY` | `2s` | How long `/health/ready` answers 503 before the listener closes. |
| `SHUTDOWN_TIMEOUT` | `5s` | How long in-flight requests may take to finish after that. |

## Shutdown

On SIGINT or SIGTERM `serve` flips `/health/ready` to 503, waits `SHUTDOWN_DRAIN_DELAY` so a proxy stops routing to it, stops accepting connections, lets in-flight requests finish within `SHUTDOWN_TIMEOUT`, closes resources in reverse order (the database pool last), and exits 0. A second signal kills the process. The defaults finish within Docker's 10-second stop grace period.

## Layout

```
cmd/workouts/                 the binary: subcommand dispatch, logger, signal handling
migrations/                   goose migrations (SQL embedded, River as Go migration 00004); sqlc's schema
sql/<module>/                 sqlc queries, one directory per module
sqlc.yaml                     sqlc configuration
internal/kernel/apperr/       typed application errors (NotFound, Conflict)
internal/kernel/clock/        Clock interface (tests inject a fixed time)
internal/kernel/txn/          Transactor port: InTx(ctx, fn)
internal/platform/config/     environment configuration
internal/platform/health/     /health, /health/ready, /up
internal/platform/postgres/   pool, migrator, Transactor, pgx error mapper, readiness check
internal/platform/server/     http.Server with graceful shutdown
internal/platform/testdb/     a migrated database per test (template clone)
internal/platform/httpapi/gen generated from the contract by oapi-codegen (never edit gen.go)
internal/identity/adapters/postgres/sqlcgen  generated by sqlc from sql/identity (never edit)
internal/bootstrap/           composition root: New(deps) -> *App
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

## Image

`docker/api-go/Dockerfile`: the `dev` stage is the Go toolchain used by compose; the `api` stage is `distroless/static-debian13:nonroot` with the static binary at `/workouts`, `USER nonroot`, and `HEALTHCHECK` running `workouts healthcheck`.

```bash
docker build -f docker/api-go/Dockerfile --target api --build-arg VERSION=dev -t workouts-api-go .
```

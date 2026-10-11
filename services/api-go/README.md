# Workouts API (Go)

A second backend for My Workouts, implementing the same contract as the Laravel API (`contracts/openapi.yaml`) as a hexagonal modular monolith in Go. It is built story by story in [the Go backend epic](../../planning/epics/go-backend/README.md); today it answers only the health probes. The PHP API stays the production backend.

## Run

The dev stack runs it in Docker; Go on the host is optional.

```bash
docker compose up -d api-go          # starts postgres too; http://localhost:8081
curl -i localhost:8081/health        # {"status":"ok","timestamp":"…"}
curl -i localhost:8081/health/ready  # 200, or 503 with status degraded
docker compose restart api-go        # pick up code changes (the dev container builds on start)
docker compose logs -f api-go        # JSON logs
```

With Go 1.27 on the host: `DATABASE_URL=postgres://workouts:secret@localhost:5432/workouts?sslmode=disable make run`.

## Commands

The binary is `workouts` (`cmd/workouts`):

| Command | Does |
| --- | --- |
| `workouts serve` | Runs the HTTP API on `HTTP_ADDR` until SIGINT or SIGTERM, then drains (see Shutdown). |
| `workouts healthcheck` | Exits 0 when `GET /health` on the local port answers 200. The image's `HEALTHCHECK`; distroless has no shell or curl. |

Later stories add `migrate`, `seed`, `work`, `catalog sync`, `keys generate` and `user grant-admin`.

## Develop

Run every task in `services/api-go`, or through the container: `docker compose exec -T api-go make <target>`.

| Target | Does |
| --- | --- |
| `make check` | The gate CI runs: `go mod tidy -diff`, `golangci-lint fmt --diff`, `go vet`, `golangci-lint run`, regenerate and fail on a diff, `go test -race -shuffle=on` with the coverage threshold, `govulncheck`. Run before every commit. |
| `make fmt` | Formats with gofumpt and goimports. |
| `make generate` | Regenerates `internal/platform/httpapi/gen/gen.go` from `contracts/openapi.yaml`. Commit the result. |
| `make test` | Tests with the race detector and the coverage threshold (`COVERAGE_MIN`, generated code excluded). |
| `make run` | `go run ./cmd/workouts serve`. |

golangci-lint is downloaded into `bin/` at the version pinned in the `Makefile`; oapi-codegen and govulncheck are `tool` directives in `go.mod` (`go tool …`).

Golden files: `go test ./internal/platform/health -update` rewrites `testdata/*.golden.json`; review the diff before committing.

## Configuration

Environment variables, read by `internal/platform/config`. Names shared with the PHP API keep their PHP spelling, so one `.env` serves both.

| Variable | Default | Meaning |
| --- | --- | --- |
| `DATABASE_URL` | required | Postgres connection string. Compose builds it from `DB_USERNAME`, `DB_PASSWORD` and `DB_DATABASE`. |
| `HTTP_ADDR` | `:8081` | Listen address. |
| `LOG_LEVEL` | `info` | `debug`, `info`, `warn`, `error`, or Laravel's names (`notice`, `warning`, `critical`, …). |
| `SHUTDOWN_DRAIN_DELAY` | `2s` | How long `/health/ready` answers 503 before the listener closes. |
| `SHUTDOWN_TIMEOUT` | `5s` | How long in-flight requests may take to finish after that. |

## Shutdown

On SIGINT or SIGTERM `serve` flips `/health/ready` to 503, waits `SHUTDOWN_DRAIN_DELAY` so a proxy stops routing to it, stops accepting connections, lets in-flight requests finish within `SHUTDOWN_TIMEOUT`, closes resources in reverse order, and exits 0. A second signal kills the process. The defaults finish within Docker's 10-second stop grace period.

## Layout

```
cmd/workouts/                 the binary: subcommand dispatch, logger, signal handling
internal/kernel/clock/        Clock interface (tests inject a fixed time)
internal/platform/config/     environment configuration
internal/platform/health/     /health, /health/ready, /up
internal/platform/server/     http.Server with graceful shutdown
internal/platform/httpapi/gen generated from the contract by oapi-codegen (never edit gen.go)
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

## Image

`docker/api-go/Dockerfile`: the `dev` stage is the Go toolchain used by compose; the `api` stage is `distroless/static-debian13:nonroot` with the static binary at `/workouts`, `USER nonroot`, and `HEALTHCHECK` running `workouts healthcheck`.

```bash
docker build -f docker/api-go/Dockerfile --target api --build-arg VERSION=dev -t workouts-api-go .
```

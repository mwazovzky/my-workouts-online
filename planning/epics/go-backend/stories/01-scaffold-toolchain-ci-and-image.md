# User Story: Scaffold, Toolchain, CI and Image

Create `services/api-go` as a buildable, linted, tested, containerised Go service that answers the health endpoints and nothing else, so every later story lands behind a CI gate that already exists.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

There is no Go code, no Go toolchain in CI, no Go image and no second API upstream in Docker Compose. Every domain story needs a module path, a configuration loader, structured logging, a graceful-shutdown server skeleton, a single `make check` gate and a Dockerfile before it can add a handler. The one open technical question with the widest blast radius, whether `oapi-codegen` handles the hardened OpenAPI 3.1 contract, has to be answered here, before any handler signature depends on the generated server interface.

## User-Visible Outcome

None. The web SPA and iOS app behave identically; the Go service answers only `/health`, `/health/ready` and `/up` on a new port.

## Scope

- `services/api-go/go.mod`: module `github.com/mwazovzky/my-workouts-online/services/api-go`, latest stable Go with the `toolchain` directive pinned.
- `cmd/workouts/main.go`: a stdlib `flag` subcommand dispatcher with `serve` only; later stories add `migrate`, `seed`, `work`, `catalog sync`, `keys generate`, `user grant-admin`, `healthcheck` (the last one lands here because the Docker `HEALTHCHECK` needs it).
- `internal/platform/config`: a `Config` struct loaded by `caarlos0/env/v11` with `envDefault` and `required` tags and validated in `Load()`. Reuse the PHP names where values will be shared (`APP_ENV`, `FRONTEND_URL`, `JWT_*`, `API_RATE_LIMIT_PER_MINUTE`); new `HTTP_ADDR` (default `:8081`), `ADMIN_ADDR`, `DATABASE_URL`, `LOG_LEVEL`.
- `log/slog` JSON handler to stderr, set once in `cmd`; fields `request_id`, `method`, `path`, `status`, `duration_ms` arrive with the middleware in story 3.
- Graceful shutdown: `signal.NotifyContext`, readiness flips to 503, `http.Server.Shutdown` with a timeout, then pool close (the pool arrives in story 2; the hook order is fixed now). `ReadHeaderTimeout` set.
- `/health` → `{"status":"ok","timestamp":…}`, `/health/ready` → `{"status":"ok|degraded","components":{…},"timestamp":…}` with 503 when degraded, `/up` → 200; the same JSON as `services/api-php/routes/web.php`. Components are empty until the pool (story 2) and JWT keys (story 4) register themselves.
- `Makefile`: `make check` = `gofumpt -l`, `go vet`, `golangci-lint run`, `go generate ./... && git diff --exit-code`, `sqlc diff` (no-op until story 2), `go test -race -shuffle=on -coverprofile` with a threshold script that excludes generated code, `govulncheck`. Also `make generate`, `make test`, `make run`.
- `.golangci.yml` (v2): govet, staticcheck, errcheck, errorlint, revive, gocritic, unparam, unused, ineffassign, bodyclose, noctx, rowserrcheck, sqlclosecheck, exhaustive, gosec, `depguard` encoding the dependency table from the epic README (`kernel` imports nothing internal; `platform` imports `kernel`; module `app`/`ports` import only other modules' `*api` packages; adapters never cross modules), `gomodguard` blocking `github.com/pkg/errors` and `testify`; formatters gofumpt + goimports.
- `docs/adr/0001-repository-layout.md`, `0002-library-choices.md`, `0003-openapi-code-generation.md` in MADR format.
- Codegen spike recorded in ADR 0003: run `oapi-codegen` v2 (strict server, `std-http`) against the hardened `contracts/openapi.yaml`; note how `type: [X, 'null']`, `allOf` in `Problem`/admin schemas and the concrete `WorkoutPage` come out. Fallbacks in order: a build-time `openapi-down-convert` step to 3.0 (artifact untracked), then `ogen`. The chosen path is the one later stories generate from.
- `docker/api-go/Dockerfile`: `golang` build stage with `CGO_ENABLED=0 go build -trimpath -ldflags "-s -w -X main.version=…"`, final `gcr.io/distroless/static-debian12:nonroot`, `USER nonroot`, `HEALTHCHECK` running `workouts healthcheck` (no shell, no curl in distroless).
- `docker-compose.yml`: `postgres:17` (volume `postgres_data`, healthcheck) and `api-go` (bind-mount or image, host port `8081:8081`, `depends_on` postgres healthy). `.env.example` gains `DATABASE_URL` and `HTTP_ADDR`. Nothing in nginx changes yet.
- `.github/workflows/go.yml`: path-filtered on `services/api-go/**`, `contracts/**`, `catalog/**` and the workflow file; runs exactly `make check`.
- `services/api-go/README.md` (how to run, test, generate), a `services/api-go` command block in `CLAUDE.md`, a Go section in `docs/architecture.md`.
- A Go section in `docs/code-style.md`, the written half of the standard that `.golangci.yml` only partly enforces: module layout and the dependency table (what `depguard` checks and what it cannot), error-handling conventions (`apperr`, `%w`, `errors.Is/As` at the boundary only), context rules (infra values only), constructor and dependency-struct conventions, no globals or `init()`, testing conventions (hand-written fakes, template databases, golden files, injected clock), and when a decision needs an ADR. Each rule says whether a linter or a reviewer enforces it.
- `docs/adr/0000-template.md` in MADR format (Context, Decision, Consequences, Status), used by ADR 0001 to 0003 here and every later one.
- Every disabled, customised or project-specific rule in `.golangci.yml` (depguard entries, gomodguard blocks, revive overrides) carries a comment naming the `docs/code-style.md` section or ADR that justifies it.

## Non-Goals

- any database access, migrations or sqlc (story 2)
- any contract operation, middleware or error envelope (story 3)
- nginx routing to the Go upstream (story 13)
- production compose or deploy workflow changes (story 13)

## Acceptance Criteria

- `make check` is green locally and in the `go` workflow on the PR
- `docker compose up -d api-go` starts and `curl -i localhost:8081/health` returns the PHP-identical JSON; `/health/ready` returns 200 with an empty `components` map
- sending `SIGTERM` to a running `workouts serve` drains in-flight requests, returns 503 from `/health/ready` during the drain, and exits 0
- the built image runs as non-root, has no shell, and its `HEALTHCHECK` passes
- ADR 0003 states which generator path was chosen and why, with the generator version and the exact diagnostics seen
- the `go` job is skipped on a PR that touches only `apps/web`
- `docs/code-style.md` has a Go section whose dependency table matches the `depguard` config, and every non-default rule in `.golangci.yml` is cross-referenced from it or from an ADR

## Initial Tasks

- [ ] `go mod init`, `cmd/workouts`, `internal/platform/config`, slog setup
- [ ] health endpoints and graceful shutdown, with a test using `httptest` and a cancelled context
- [ ] `Makefile`, `.golangci.yml`, coverage threshold script
- [ ] ADR 0001, 0002; run the codegen spike and write ADR 0003
- [ ] Dockerfile, compose services, `.env.example`
- [ ] `go.yml` workflow with the path filter
- [ ] README, `CLAUDE.md` block, `docs/architecture.md` section
- [ ] Go section in `docs/code-style.md`, ADR template, rule comments in `.golangci.yml`

## Test Cases

- `make check` fails on an unformatted file, on a lint violation and on a stale generated file (verified once by hand, then trusted)
- health handlers: 200 bodies match golden JSON; readiness returns 503 after shutdown starts
- config: missing required variable fails `Load()` with the variable name in the error
- CI: `go` job green; `tests`, `lint`, `contract` and `ios` jobs unaffected

## Docs To Update

- `docs/architecture.md` (new Go service section: layout, dependency rules, `make check`)
- `docs/code-style.md` (new Go section: the written conventions the linter does not cover)
- `CLAUDE.md` (Go command block)
- `services/api-go/README.md` (new)

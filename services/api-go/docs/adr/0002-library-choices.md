# 0002. Library and Tool Choices

- Status: accepted
- Date: 2026-10-11
- Story: [Go epic story 01](../../../../planning/epics/go-backend/stories/01-scaffold-toolchain-ci-and-image.md)

## Context

The service should read as idiomatic, current Go: the standard library first, a few widely adopted libraries where the standard library has no answer, no web framework, no ORM and no dependency-injection container. Every dependency is a long-lived maintenance cost for a solo maintainer, and every tool version must be reproducible in CI and in the dev container without Go on the host.

## Considered Options

For each concern, the standard library or the de-facto standard tool, against a framework (Gin, Echo, Fiber, GORM, Cobra, Viper, testify, wire). The frameworks save some typing but hide control flow, add transitive dependencies and teach their API instead of Go's.

## Decision

Adopted in story 1:

| Concern | Choice | Version |
| --- | --- | --- |
| Language | Go, `go 1.27.0` with `toolchain go1.27.2` in `go.mod`; the Docker image pins `golang:1.27.2` | 1.27.2 |
| HTTP server and router | `net/http` (`ServeMux` method patterns, `http.Server` with explicit timeouts) | stdlib |
| CLI | `flag` with one `FlagSet` per subcommand | stdlib |
| Logging | `log/slog` JSON handler to stderr | stdlib |
| Configuration | `github.com/caarlos0/env/v11`: struct tags with defaults and `required`, parsed from an explicit map so tests need no process environment | v11.4.1 |
| HTTP code generation | `github.com/oapi-codegen/oapi-codegen/v2` (ADR 0003), a `tool` directive in `go.mod`, run with `go tool oapi-codegen` | v2.8.0 |
| Test diffs | `github.com/google/go-cmp` | v0.7.0 |
| Lint and format | golangci-lint v2 (linters plus the gofumpt and goimports formatters), installed by the official script at the tag pinned in the `Makefile` | v2.14.0 |
| Vulnerabilities | `golang.org/x/vuln/cmd/govulncheck`, a `tool` directive | v1.8.0 |
| Runtime image | `gcr.io/distroless/static-debian13:nonroot` | — |

Adopted in story 2:

| Concern | Choice | Version |
| --- | --- | --- |
| Postgres driver and pool | `github.com/jackc/pgx/v5` with `pgxpool`; `pgx/v5/stdlib` gives goose a `*sql.DB` over the same pool | v5.11.0 |
| Typed queries | sqlc (`sqlc.yaml`): hand-written SQL in `sql/<module>/`, the migrations as schema, one `sqlcgen` package per module. Installed as the release binary at `SQLC_VERSION` in the `Makefile`, not a `tool` directive: building it needs cgo and a large dependency tree | v1.31.1 |
| Migrations | `github.com/pressly/goose/v3` through its `Provider` API (no global registry, no `init()`), SQL files embedded from `migrations/`, a Postgres advisory lock against concurrent runs, `workouts migrate up\|status` | v3.28.0 |
| Job queue schema | `github.com/riverqueue/river` (`rivermigrate` with the `riverdatabasesql` driver) installs River's tables as goose migration 00004, pinned to River schema version 8; the queue itself arrives in story 11 | v0.49.0 |
| Test databases | `github.com/testcontainers/testcontainers-go/modules/postgres` (`postgres:17`) when `TEST_DATABASE_URL` is unset; see `internal/platform/testdb` | v0.44.0 |

Upgraded for govulncheck in story 2 (both reached only through testcontainers): `golang.org/x/crypto` v0.56.0, `github.com/moby/go-archive` v0.3.0.

Adopted in story 3:

| Concern | Choice | Version |
| --- | --- | --- |
| Request and response validation | `github.com/getkin/kin-openapi/openapi3filter` against the embedded contract (ADR 0006); upgraded from v0.142.0 for GO-2026-6112, a panic in request validation | v0.144.0 |
| Locale negotiation | `golang.org/x/text/language` (`ParseAcceptLanguage`), already in the module graph | v0.42.0 |
| Request ids | `github.com/google/uuid`, already in the module graph through the generated code | v1.6.0 |

Messages, validation and pagination are hand-written kernel packages (`i18n`, `validate`, `pagination`), and the rate limiter is an in-memory fixed window behind a port (`platform/ratelimit`); no library was needed for any of them.

Planned, each confirmed or replaced by an ADR in its story: `lestrrat-go/jwx/v3` and `golang.org/x/crypto/bcrypt` (story 4), `resend-go` and `svix` (story 11), the River client and workers (story 11), OpenTelemetry (story 12).

Rejected: `testify` and generated mocks (blocked by `gomodguard_v2`; tests use `testing`, go-cmp and hand-written fakes), `github.com/pkg/errors` (blocked; `fmt.Errorf` with `%w`), Viper and Cobra (configuration and CLI are small enough for the standard library and caarlos0/env).

Tools are pinned in one place each: `tool` directives in `go.mod` for oapi-codegen and govulncheck; `GOLANGCI_LINT_VERSION` and `SQLC_VERSION` in the `Makefile` for golangci-lint and sqlc, installed as release binaries so their dependencies stay out of `go.mod` (golangci-lint's maintainers recommend this over `go install` or `go tool`).

## Consequences

- Good: `go.mod` stays short; the code reads like the Go documentation; CI and the dev container run the same pinned tools through `make check`.
- Bad: some things a framework provides (route groups, binding, middleware chains) are written by hand in story 3. Accepted; the generated strict server covers binding.
- Bad: the oapi-codegen `tool` directive pulls its dependency graph into `go.sum`. It does not reach the binary, and govulncheck reports only reachable code.
- Follow-up: bump the Go toolchain, `golang` image tag and golangci-lint together in one PR; revisit a row when its story finds the choice wrong.

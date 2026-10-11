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

Planned, each confirmed or replaced by an ADR in its story: `pgx/v5` + `pgxpool` and `sqlc` (story 2), `pressly/goose` (story 2), `testcontainers-go` (story 2), `kin-openapi` `openapi3filter` for request validation (story 3, already a dependency of the generated code), `lestrrat-go/jwx/v3` and `golang.org/x/crypto/bcrypt` (story 4), `resend-go` and `svix` (story 11), River (story 11), OpenTelemetry (story 12).

Rejected: `testify` and generated mocks (blocked by `gomodguard_v2`; tests use `testing`, go-cmp and hand-written fakes), `github.com/pkg/errors` (blocked; `fmt.Errorf` with `%w`), Viper and Cobra (configuration and CLI are small enough for the standard library and caarlos0/env).

Tools are pinned in one place each: `tool` directives in `go.mod` for oapi-codegen and govulncheck; `GOLANGCI_LINT_VERSION` in the `Makefile` for golangci-lint, whose maintainers recommend a binary install over `go install` or `go tool` so its dependencies stay out of `go.mod`.

## Consequences

- Good: `go.mod` stays short; the code reads like the Go documentation; CI and the dev container run the same pinned tools through `make check`.
- Bad: some things a framework provides (route groups, binding, middleware chains) are written by hand in story 3. Accepted; the generated strict server covers binding.
- Bad: the oapi-codegen `tool` directive pulls its dependency graph into `go.sum`. It does not reach the binary, and govulncheck reports only reachable code.
- Follow-up: bump the Go toolchain, `golang` image tag and golangci-lint together in one PR; revisit a row when its story finds the choice wrong.

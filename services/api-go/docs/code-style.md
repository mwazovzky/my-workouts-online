# Go Code Style

Coding standards for the Go API. Repository-wide conventions (commit messages, API design in [`docs/api-design.md`](../../../docs/api-design.md)) still apply; the PHP and web standards are in [`docs/code-style.md`](../../../docs/code-style.md).

Three layers with one job each: `.golangci.yml` enforces what a linter can check, this document states every convention in prose, including the ones no linter can check, and the ADRs in [`adr/`](adr/) record why. Each rule below ends with who enforces it: **(lint: name)** for a linter, **(make check)** for another automated step, **(review)** when only a reviewer can.

- `make check` **must** pass before every commit; CI runs exactly `make check` **(make check)**.
- A decision **must** get an ADR (copy [`adr/0000-template.md`](adr/0000-template.md)) when it picks a library or tool, changes the module layout or dependency table, departs from the PHP API's behaviour or schema, or disables a lint rule for the whole service **(review)**.

## Module Layout and Dependencies

`internal/` is split into `kernel` (dependency-free shared types), `platform` (infrastructure), one directory per module (`identity`, `catalog`, `training`, `dashboard`) and `bootstrap` (the composition root). A module has `domain/`, `app/` (use cases), `ports/` (interfaces), `adapters/` and an exported `<module>api/` package, its only surface for other modules. [ADR 0001](adr/0001-repository-layout.md) has the reasoning.

| Package | May import (internal) | Enforced by |
| --- | --- | --- |
| `kernel/*` | other `kernel` packages only | lint: depguard `kernel` |
| `platform/*` | `kernel`, other `platform` packages | lint: depguard `platform` |
| `<module>/domain`, `app`, `ports` | `kernel`, own module's core, other modules' `*api` packages as allowed below; never `platform`, adapters or `bootstrap` | lint: depguard `module-core` |
| `<module>/adapters/*` | `kernel`, `platform`, own module; never another module, not even its `*api` package | lint: depguard `adapters` |
| `identity`, `catalog` | no other module | lint: depguard `identity`, `catalog` |
| `training` | `catalog/catalogapi` only | lint: depguard `training` |
| `dashboard` | `catalog/catalogapi`, `training/trainingapi` only | lint: depguard `dashboard` |
| `bootstrap` | everything; imported only by `cmd/` | review |

- A use case **must** write the tables of one module only; no transaction spans two modules. `dashboard` is a read model and may read across tables **(review)**.
- An `*api` package **must** export plain data types and narrow interfaces, never a module's domain types or its repository **(review)**.
- Each module **must** have a `doc.go` stating what it owns **(review)**.

## Errors

- Errors **must** be wrapped with `fmt.Errorf("…: %w", err)` and matched with `errors.Is` / `errors.As`, never by comparing strings or with `==` on wrapped errors **(lint: errorlint)**.
- Business failures **must** be typed `kernel/apperr` errors (`NotFound`, `Conflict`, `Validation`, `Unauthenticated`, `Forbidden`, `RateLimited`, `PreconditionFailed`, `PreconditionRequired`, `BadRequest`), each with the contract's `ErrorCode`; only `httpapi.Responder` maps them to a status and a Problem Details body ([ADR 0006](adr/0006-http-errors-locale-and-request-validation.md)). Use cases and domain code never know a status code **(review)**. A code outside the contract's enum fails a test **(make check)**.
- An error's `Detail` and a field error's `Detail` **must** be English message keys: a sentence keyed by itself, or a Laravel-style key (`validation.required`); never text built with `fmt.Sprintf`. Placeholders are `:name`, filled from `Args` **(review)**. Every key **must** exist in every `kernel/i18n/locales/*.json` with the same placeholders **(make check)**.
- Adapters **must** translate driver errors (`pgx`, `pgconn`) into `apperr` before returning, through `platform/postgres.MapError` (no rows is `NotFound`; a unique or foreign-key violation is `Conflict`); no `pgx` type crosses a port. To turn a conflict into a field error, switch on `postgres.ConstraintName(err)`, never on the message **(review)**.
- Every returned error **must** be handled, and every response body and `sql.Rows` closed **(lint: errcheck, bodyclose, rowserrcheck, sqlclosecheck)**.
- `github.com/pkg/errors` **must not** be used **(lint: gomodguard_v2)**.

## Context

- `context.Context` **must** be the first parameter of every function below the HTTP handler that does I/O or may block **(review)**; outbound HTTP requests and listeners take one **(lint: noctx)**.
- The context **must** carry only infrastructure values: request id, logger, principal, trace. Business inputs **must** be explicit fields of a use-case input struct, never context values **(review)**.

## Types

- Domain invariants **must** live on domain types (constructors and methods that return an error), not in handlers or SQL; a use case orchestrates, a handler translates **(review)**.
- A `switch` over an enum, including the generated contract enums, **must** list every value; a `default` branch does not count **(lint: exhaustive)**.
- Request and response types **must** come from the generated package (`internal/platform/httpapi/gen`); hand-written structs mirroring the contract are not allowed **(review)**.

## Constructors and State

- Constructors **must** take their dependencies explicitly, as parameters or a `Deps` struct of interfaces and values; nothing reaches for a global **(review)**.
- No package-level mutable state and no `init()` functions; `cmd/workouts` sets the default `slog` logger once and passes loggers on explicitly. The exceptions are test infrastructure: `platform/testdb` (the template database lives for one test binary) and `platform/httpapi/apitest` (the `-update` flag) **(review)**.
- Time **must** come from an injected `kernel/clock.Clock`, never `time.Now()` in use cases or handlers **(review)**.

## Dependencies

- A new module dependency **must** be justified in [ADR 0002](adr/0002-library-choices.md) or a new ADR **(review)**.
- Tools (`oapi-codegen`, `govulncheck`) are pinned as `tool` directives in `go.mod` and run with `go tool`; golangci-lint and sqlc are pinned in the `Makefile` and installed into `bin/` **(make check)**.
- Blocked modules: `github.com/pkg/errors`, `github.com/stretchr/testify`, `github.com/golang/mock`, `go.uber.org/mock` **(lint: gomodguard_v2)**.
- `go.mod` **must** be tidy **(make check: `go mod tidy -diff`)**; no reachable known vulnerability **(make check: govulncheck)**.

## Generated Code

- Generated code (oapi-codegen output under `internal/platform/httpapi/gen`, sqlc output under `internal/<module>/adapters/postgres/sqlcgen`) **must** be committed and **must not** be edited; `make generate` rewrites it, and `make check` fails when it is stale **(make check)**. A module's first query adds its `sqlc.yaml` block and its `sqlcgen` path to `GENERATED` in the `Makefile` **(review)**.

## HTTP

- An operation is implemented as a method of the generated `StrictServerInterface` on a module's handler type, which is embedded in `bootstrap/api.go`; the operation's stub in `bootstrap/unimplemented.go` **must** be deleted in the same change. Leaving it makes the method ambiguous and the build fails **(make check)**.
- Handlers **must not** re-check what the contract already constrains (types, enums, lengths, unknown fields): the request validation middleware rejects those with 422 before the handler runs. Handlers and use cases validate business rules with `kernel/validate` **(review)**.
- Who may call an operation comes from the contract, never from handler code: `security: []` is public, anything else needs a bearer token, and the `Admin` tag needs an admin ([ADR 0007](adr/0007-identity-tokens-and-auth-policy.md)). Handlers read the caller with `auth.FromContext` **(review)**.
- Per-operation throttles are a map from operationId to limit exported by the module's HTTP adapter (`identityhttp.Throttles`) and passed to the server in `bootstrap` **(review)**.
- What the contract leaves undeclared (the refresh cookie, the client IP) is read through `httpapi.Request(ctx)`; declared parameters and bodies only through the generated request object **(review)**.
- A header the contract does not declare (`Cache-Control`) is set by wrapping the generated response type and overriding its `Visit…Response` method, as `platform/meta` does; handlers never take a `ResponseWriter` **(review)**.
- Every new user-facing message **must** be added to both locale catalogs **(make check)**.

## Database

- The schema changes only through a new goose migration in `migrations/`, numbered after the last one; an applied migration is never edited. Each has a `-- +goose Down` section that undoes it **(review)**; the migration tests apply every migration, go down to zero and up again **(make check)**.
- Queries **must** be sqlc queries in `sql/<module>/`, touching that module's tables only (`dashboard` reads across) **(review)**. Name `:one` queries `Get…`, `:many` queries `List…`, writes `Create…`, `Update…`, `Delete…` **(review)**.
- Constraints are named explicitly (`<table>_<columns>_key`, `<table>_<column>_check`) so adapters can map them; closed sets from the contract are `text` with a `CHECK`, not Postgres enum types, so adding a value is a one-line migration **(review)**.
- A repository **must** take its `DBTX` from `Transactor.Querier(ctx)` on every call, so it joins the transaction a use case opened with `InTx`; repositories never begin transactions themselves **(review)**.
- Generated code is excluded from linting and from the coverage threshold **(make check)**.

## Testing

- Tests use the standard library `testing` package and `go-cmp` for diffs; no assertion or mocking frameworks **(lint: gomodguard_v2)**.
- Ports **must** get hand-written fakes in the test package that needs them, not generated mocks **(review)**.
- Tests **should** be table-driven and call `t.Parallel()`; the suite runs with `-race -shuffle=on`, so tests **must not** depend on order or shared state **(make check)**.
- Tests **must not** sleep to wait for something; synchronise with channels and inject a fixed `Clock` **(review)**.
- HTTP behaviour **must** be tested through the real handler from `bootstrap.New` with `platform/httpapi/apitest`. It validates every response to a contract operation against the contract and fails the test on a mismatch **(review)**.
- Response bodies are compared with golden files in `testdata/`, as JSON values (`go test ./internal/<package> -update` rewrites them; review the diff). A golden file copied from the PHP API's response proves parity **(review)**. Reading them by a computed path is a `gosec` G304 exception, and test fixtures (passwords, the phpunit key pair) a G101 exception, both only in `_test.go` files.
- A test that touches the database **must** get its own with `testdb.New(t)` (migrated) or `testdb.NewEmpty(t)` (for migration tests), and its package **must** declare `func TestMain(m *testing.M) { testdb.Main(m) }`; no test shares a database with another **(review)**. Tests use the compose Postgres when `TEST_DATABASE_URL` is set (the `api-go` container sets it) and a testcontainers Postgres otherwise (CI) **(make check)**.
- Statement coverage, generated code excluded and measured across packages (`-coverpkg=./...`, so HTTP tests count for the handlers they exercise), **must** stay at or above `COVERAGE_MIN` in the `Makefile` **(make check)**.
- Each domain story **must** port the PHPUnit feature tests it replaces case by case (owner allowed, other user 404, validation failures) **(review)**.

## Formatting

- Code is formatted with gofumpt and goimports, with the service's own packages in a separate import group (`make fmt`) **(make check: `golangci-lint fmt --diff`)**.

## Linting

- The standard golangci-lint set (errcheck, govet, ineffassign, staticcheck, unused) plus gocritic, gosec, revive (doc comments on exported names) and unparam **must** pass **(lint)**. A `//nolint` directive **must** name the linter and give the reason **(lint: nolintlint)**.
- A rule may be disabled or customised only in `.golangci.yml`, with a comment naming the section of this document or the ADR that justifies it **(review)**.

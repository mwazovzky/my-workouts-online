# 0001. Repository Layout: a Hexagonal Modular Monolith

- Status: accepted
- Date: 2026-10-11
- Story: [Go epic story 01](../../../../planning/epics/go-backend/stories/01-scaffold-toolchain-ci-and-image.md)

## Context

The Go API implements the same contract as the Laravel API (`contracts/openapi.yaml`, 56 operations) as a learning project in idiomatic Go architecture. The domain splits naturally into users and tokens, the shared catalog (equipment, exercises, templates, programs), personal workouts, and a dashboard read model. Laravel's layout (controllers, services, Eloquent models in one namespace each) lets any class reach any table; the Go service should make module boundaries visible and checkable, without the cost of separate deployables.

## Considered Options

1. Flat layered packages (`handlers/`, `services/`, `repositories/`) — familiar, but every package depends on every domain and nothing stops cross-domain writes.
2. Microservices per domain — real boundaries, but a second backend for a solo maintainer cannot afford several deployables, and the contract is one API.
3. A modular monolith with hexagonal modules and a linted dependency table.

## Decision

Option 3. One Go module, `github.com/mwazovzky/my-workouts-online/services/api-go`, one binary `cmd/workouts`, and under `internal/`:

```
kernel/      dependency-free shared types: clock now; apperr, i18n, validate, pagination, auth principal, txn later
platform/    infrastructure: config, health, server, httpapi/gen (generated), postgres, observability, mail, jobs, testdb
identity/    users, roles, refresh tokens, verification, password reset, /me
catalog/     equipment, categories, exercises, templates, programs, enrollment, admin CRUD, catalog sync
training/    workouts, workout activities and sets
dashboard/   read model over catalogapi and trainingapi
bootstrap/   composition root: New(deps) -> *App; imported only by cmd/
```

Each module has `domain/` (types and invariants), `app/` (use cases), `ports/` (interfaces the use cases need), `adapters/` (postgres, http, and jwt, bcrypt, links, catalogfile where needed) and `<module>api/`, the only package another module may import. The allowed arrows are: `kernel` ← `platform` ← adapters; a module's core imports `kernel`, its own packages and the permitted `*api` packages (`training → catalogapi`; `dashboard → catalogapi, trainingapi`; `identity` and `catalog` import no module); adapters never cross modules. `depguard` in `.golangci.yml` encodes this table and [`docs/code-style.md`](../code-style.md) ("Module Layout and Dependencies") states it in prose, including what the linter cannot check (one module's tables per transaction).

Story 1 creates `cmd/workouts`, `kernel/clock`, `platform/{config,health,server,httpapi/gen}` and `bootstrap`; each module directory appears with the story that first needs it.

## Consequences

- Good: a cross-module import fails `make check` with the reason, so boundaries survive refactoring; each module can be read, tested and later extracted on its own.
- Good: the composition root is the one place that knows every concrete type, which keeps constructors honest and the HTTP tests able to build the real application.
- Bad: more packages and some duplicated plain types at module edges (an `*api` DTO next to a domain type). Accepted as the price of visible boundaries.
- Bad: `depguard` matches import-path prefixes, so the rules list modules by name; a new module needs new rules in the same PR.
- Follow-up: revisit if a module needs a transaction spanning another module's tables; that is a sign the boundary is wrong, not a reason to relax the rule.

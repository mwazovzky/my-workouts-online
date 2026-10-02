# User Story: Generated API Client

Generate the Swift client from `contracts/openapi.yaml` at build time and wrap it with locale and user-agent middlewares, a typed error, and domain mappers.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

The app needs a typed client that cannot drift from the contract. Hand-written models would drift silently; generated code committed to the repo would drift when someone forgets to regenerate.

## User-Visible Outcome

None.

## Scope

- `Packages/WorkoutsAPI`: dependencies `swift-openapi-generator` (plugin), `swift-openapi-runtime`, `swift-openapi-urlsession`; `Sources/WorkoutsAPI/openapi.yaml` as a relative symlink to `contracts/openapi.yaml`; `openapi-generator-config.yaml` with `generate: [types, client]`, `accessModifier: public`, `namingStrategy: idiomatic`, `filter.tags` = Auth, Me, Meta, Dashboard, Programs, Workout Templates, Exercises, Workouts
- `APIClientFactory.make(baseURL:middlewares:)` returning the generated `Client` over `URLSessionTransport`
- `LocaleMiddleware` (sets `Accept-Language` from an injected resolver), `UserAgentMiddleware` (`MyWorkouts-iOS/{version} ({build})`)
- `APIError`: `.validation([field: [FieldError(code, message)]])`, `.api(status, code, message)`, `.network(underlying)`, `.decoding(underlying)`, with helpers mapping generated `undocumented` payloads and the 422 `ValidationError` envelope; field keys keep the dot path (`activities.0.sets.1.effort_value`)
- `WorkoutsCore` domain models and mappers from `Components.Schemas.*`: `User`, `Meta`, `Program`, `ProgramDetail`, `WorkoutTemplate`, `Exercise`, `Workout`, `Activity`, `WorkoutSet`, `Dashboard`, `Page<T>` (from `{data, links, meta}`); enums `EffortType`, `DifficultyUnit`, `WorkoutStatus`, `ThemePreference`
- `StubTransport: ClientTransport` test helper scripted with responses and recording requests, shared by later stories' tests
- JSON fixtures captured from the seeded dev API for each domain model

## Non-Goals

- bearer injection and refresh (story 4)
- any view or repository beyond what mappers need

## Acceptance Criteria

- the package builds with zero generator diagnostics for the filtered tags
- mappers reference the four properties reshaped in story 1
- a stubbed `GET /me` round-trips to a `User`; a stubbed 422 maps to field errors keyed by dot path; a 500 maps to `.api`
- `Accept-Language` and `User-Agent` are present on every recorded request

## Initial Tasks

- [ ] package manifest, symlink, generator config, plugin wiring
- [ ] factory and middlewares
- [ ] `APIError` and mapping helpers
- [ ] domain models and mappers
- [ ] fixtures and tests

## Test Cases

- fixture decoding for every domain model, including null `workout_template` and null `in_progress_workout`
- `LocaleMiddleware` and `UserAgentMiddleware` header tests
- 401, 422, 404, 500 mapping tests
- manual, documented: renaming a field in a scratch copy of the spec fails compilation

## Docs To Update

- `docs/architecture.md` (client generation subsection under the contract)

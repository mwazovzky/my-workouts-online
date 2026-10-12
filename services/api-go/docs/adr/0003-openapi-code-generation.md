# 0003. Generate the HTTP Layer with oapi-codegen from the 3.1 Contract

- Status: accepted
- Date: 2026-10-11
- Story: [Go epic story 01](../../../../planning/epics/go-backend/stories/01-scaffold-toolchain-ci-and-image.md)

## Context

The epic requires zero hand-written request or response structs: the Go server is generated from `contracts/openapi.yaml`, so a contract change that removes or renames a field breaks the Go build, as it already breaks the iOS build. The contract is OpenAPI 3.1.0 (contract version 2.0.0 after the Contract Hardening epic) and uses constructs that 3.0-era generators often mishandle:

- `type: [X, 'null']` for nullable fields (about 40 places), including inlined nullable enums;
- flat `Problem` and `ValidationProblem` schemas (RFC 9457) served as `application/problem+json`, with `ErrorCode` and `FieldErrorCode` enums;
- a concrete `WorkoutPage` (`data`, `links`, `meta`) instead of a generic `allOf` page;
- `If-Match` as a required header parameter and `ETag` as a response header on the workout operations;
- untyped inline objects (`/.well-known/jwks.json` keys) and `additionalProperties` maps (`TranslationMap`, readiness `components`).

The fallbacks, in order, were a build-time `openapi-down-convert` to 3.0 (artifact untracked) and then `ogen`.

## Considered Options

1. oapi-codegen v2 strict server on the stdlib router (`std-http-server`, `strict-server`, `models`, `embedded-spec`) directly on the 3.1 file.
2. oapi-codegen on a down-converted 3.0 copy.
3. ogen, which generates its own router, encoders and validation.

## Decision

Option 1. The spike ran oapi-codegen v2.8.0 (`go tool oapi-codegen -config internal/platform/httpapi/gen/oapi-codegen.yaml contracts/openapi.yaml`) on the unchanged contract:

- Diagnostics: none. The generator printed no warning or error, `go vet` is clean, and the 9,137-line `gen.go` compiles.
- The embedded spec loads with kin-openapi v0.142.0 (`gen.GetSwagger()`: `openapi: 3.1.0`, 37 paths) and `Validate()` returns nil, so story 3 can build `openapi3filter` request validation on it.
- The strict server interface has 57 methods, one per `operationId` in the contract (including `socialLogin`, `x-status: target`).
- `type: [X, 'null']` becomes a pointer (`*string`, `*int`, `*time.Time`) without `omitempty` when the field is required, so `null` is written, as the contract requires (`User.email_verified_at`, `ActivityExercise.name`, `PageLinks.next`).
- Optional object fields documented as omitted (`Activity.exercise`, `Dashboard.in_progress_workout`) become pointers with `omitempty`.
- `Problem` and `ValidationProblem` are flat structs with typed `Code` enums; every `application/problem+json` response has its own response type that sets that content type and the `X-Request-Id` header.
- `WorkoutPage` is a plain struct of `[]Workout`, `PageLinks` and `PageMeta`.
- `If-Match` binds into `ReplaceWorkoutActivitiesParams.IfMatch` (required); `ETag` is a typed response header written by the response visitor.

Findings that later stories must handle, none blocking:

- Unformatted `number` becomes `float32` (`Set.difficulty_value`). The values have one decimal and `encoding/json` writes float32 at 32-bit precision, so `52.5` and `52.3` round-trip as written; the postgres adapter converts to `numeric(6,1)` (stories 2 and 8).
- `date-time` fields are `time.Time`, which encodes as `2026-10-07T09:30:00Z`; the PHP API writes `+00:00`. Both are RFC 3339 and match the contract.
- Enum constants are prefixed only on a name collision, so the readiness component values are `gen.Up` and `gen.Down`.
- The JWKS `keys` items are `map[string]interface{}` because the contract leaves them untyped; story 4 fills them from jwx.
- `Problem.Status` is `int32`.
- An optional nullable field becomes a pointer with `omitempty`, so `null` is dropped, while PHP sends the key with `null`. The generator hint `x-omitempty: false` fixes the fields where that shows (the localized `description` of `Program`, `WorkoutTemplate` and `Exercise`; `Workout.activities_count`), so Go writes `null` too.
- A required header the generated router checks itself: a missing `If-Match` would be rejected before any middleware or handler, where PHP answers 404 and 403 first and only then 428. The binding treats it as optional and the use case answers 428.

Generator hints live in `internal/platform/httpapi/gen/overlay.yaml`, an OpenAPI Overlay oapi-codegen applies before generating (story 8; story 6's hints, first written into the contract, moved there). The shared contract stays free of Go-only extensions, every other generator and backend reads it unchanged, and the wire format does not change; the embedded spec Go validates requests against is the overlaid one.

Wiring: `internal/platform/httpapi/gen/generate.go` holds the `//go:generate` directive and `oapi-codegen.yaml` the options; `gen.go` is committed, excluded from linting and coverage, and `make check` regenerates it and fails on any diff (`generate-check`). Story 1 uses the generated health types; story 3 wires the strict server through `bootstrap`.

## Consequences

- Good: no spec edit and no conversion step; the contract stays the single source, and the Go build now fails on a breaking contract change, as the iOS build does.
- Good: request binding, response content types and status codes come from the contract, so handlers return typed response objects.
- Bad: one large generated file to review in contract PRs. Accepted; it is diff-checked, not hand-edited.
- Bad: the generated router has no per-operation middleware hook; story 3 keys auth and throttle policy by `operationId` in a strict-handler middleware instead.
- Follow-up: if a future contract construct breaks generation, apply the fallbacks in order (3.0 down-convert, then ogen) and supersede this record.

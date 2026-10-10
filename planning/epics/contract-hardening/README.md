# Epic: Contract Hardening

**Status: shipped (2026-10-10), all seven stories merged (#56–#60, #62, #64); the contract is `2.0.0`.** Kept for reference; current truth lives in `contracts/openapi.yaml`, `docs/api-design.md` and the rest of `docs/`. The [Go Backend](../go-backend/README.md) epic is unblocked.

Bring `contracts/openapi.yaml` up to current REST conventions — RFC 9457 errors, consistent naming and HTTP semantics, generator-friendly schemas — while the API has one backend and two clients the repo controls, so the Go backend epic implements the hardened contract once instead of inheriting Laravel-shaped quirks.

## Why This Epic Exists

A review of the contract (38 paths, 56 operations) found it already sound in the large: versioned path, plural kebab-case nouns, a consistent `{data}` envelope, stable error codes, nullable handling that survives three code generators. The gaps are specific and all of one kind: Laravel idioms that leaked into the wire format. Path parameters carry route-binding names (`{program}`, `{workout}`, `{id}`), `WorkoutTemplate` exposes an Eloquent `pivot`, `password_confirmation` is a form habit, validation error codes are Laravel rule class names, `Weekday` is capitalised while every other enum is lowercase snake, bad credentials return 422, non-owner responses differ by action (404 on `show`, 403 elsewhere), and `PATCH /profile` requires every field. The error envelope `{message, code}` is a home-grown subset of RFC 9457 Problem Details, and the validation `errors` map keyed by dot paths generates badly (the iOS client already reads `value2.errors.additionalProperties`).

None of this is broken. It is cheap to change now — one web SPA, an iOS app not yet in the App Store, one backend — and expensive the moment a second backend implements the current shapes. The Go backend epic (`planning/epics/go-backend/`) starts after this epic ships.

## Decisions

| # | Decision | Choice |
| --- | --- | --- |
| 1 | Error envelope | RFC 9457 Problem Details: `{type, title, status, detail, instance, code}` with `Content-Type: application/problem+json`. `code` stays as the extension clients switch on; `title` is stable English per code; `detail` is localized. |
| 2 | Validation errors | `ValidationProblem.errors` is an array of `{pointer, code, detail}`; `pointer` is an RFC 6901 JSON Pointer (`/activities/0/sets/1/effort_value`). No map keyed by dot path. |
| 3 | Error code registries | `FieldErrorCode` enum (`required`, `invalid_format`, `invalid_value`, `too_short`, `too_long`, `out_of_range`, `not_unique`, `not_found`, `mismatch`) mapped from Laravel rules in `ApiErrorResponse`; `ErrorCode` enum listing every top-level code. Backends may not invent codes outside the spec. |
| 4 | Rulebook | Zalando RESTful API Guidelines, enforced by Spectral with the Zalando ruleset in `.github/workflows/lint.yml` next to Redocly. Rules a later story fixes are disabled with a TODO naming that story and re-enabled by it. |
| 5 | PATCH semantics | RFC 7386 JSON Merge Patch: every field optional, at least one present. `PUT` keeps full-replacement write schemas. |
| 6 | Concurrency | `ETag` on every `WorkoutResponse`; `If-Match` required on the editor write, `412 precondition_failed` on mismatch, `428 precondition_required` when absent. The tag is `"<id>-<version>"` from a `workouts.version` counter (story 6), not `updated_at`: the save did not touch `updated_at`, and second precision lets two saves in one second share a tag. Comparison is strong per RFC 9110, so the tag is strong too. |
| 7 | Ownership | 404 for any resource the caller does not own, on every action. 403 is reserved for authenticated-but-forbidden (unverified email, non-admin). |
| 8 | Login failures | Bad credentials `401 invalid_credentials`; lockout `429 too_many_attempts` with `Retry-After`. 422 is only for malformed input. |
| 9 | Path parameters | `{programId}`, `{workoutId}`, `{workoutTemplateId}`, `{equipmentId}`, `{categoryId}`, `{exerciseId}` — camelCase, suffixed `Id`, never the Laravel binding name. |
| 10 | Enums | Lowercase snake_case everywhere, including `Weekday` (`monday` … `sunday`). |
| 11 | Versioning | `info.version` bumps to `2.0.0`; the path stays `/api/v1`. A path version is for parallel incompatible APIs, which is unnecessary while every client lives in this repo. |
| 12 | Localized labels | Dropped (story 5): `status_label`, `weekday_label`, `effort_label`, `difficulty_label`, `exercise_effort_label`, `exercise_difficulty_label`. Responses send enum values; the web maps them through `t()` (`src/lib/enumLabels.js`) and iOS through `Localizable.xcstrings`, with the same text the API used to render. Catalog content (`name`, `description`) stays localized by `Accept-Language`. |
| 13 | Delivery | One PR per story touching the contract, PHP, web and iOS together, so the contract job, the web suite and the iOS build are all green on every merge. |
| 14 | Standards documentation | Three layers, each with one job: the linter enforces what can be checked mechanically (`contracts/.spectral.yaml`, `redocly.yaml`); `docs/api-design.md` states every rule in prose, semantic rules included (ownership 404, 422 vs 401, how to add an error code), and says per rule whether Spectral or a reviewer enforces it; this README's Decisions table records why. Every disabled or customised lint rule carries a comment naming its story or standard section. Lands in story 1 so later stories follow a written rule, not a memory. |

## Target Architecture

```
Problem (every non-2xx, Content-Type: application/problem+json)
{
  "type": "https://my-workouts.example/problems/validation_failed",
  "title": "Validation failed",
  "status": 422,
  "detail": "Проверьте введённые данные.",
  "instance": "/api/v1/workouts/42/activities#req_01J9ZK...",
  "code": "validation_failed",
  "errors": [
    { "pointer": "/activities/0/sets/1/effort_value", "code": "out_of_range", "detail": "..." },
    { "pointer": "/activities/1/exercise_id",         "code": "not_found",    "detail": "..." }
  ]
}

Client switch points that must keep working:
  web  apps/web/src/lib/api.js            code === 'token_expired'   -> refresh and retry
  iOS  AuthMiddleware.swift               code == "token_expired"    -> refresh and retry
  iOS  AuthSession.swift                  code.hasPrefix("refresh_token") -> sign out
  both useApiForm.js / FormErrors.swift   errors[].pointer -> field
```

## Scope

- the contract, `info.version` 2.0.0
- `services/api-php`: exception rendering, form requests, policies, resources, routes, one enum, two migrations
- `apps/web`: API client, form composable, enrollment composable, affected pages, regenerated `types.d.ts`
- `apps/ios`: `WorkoutsAPI` error mapping and middleware, `WorkoutsCore` repositories, mapping, draft store, fixtures
- CI: Spectral in `lint.yml`; Schemathesis unchanged except for the operation ids it excludes
- `docs/` updated as each story ships

## Non-Goals

- a `/api/v2` path or running two contract versions side by side
- cursor-based pagination, `Idempotency-Key`, `RateLimit-*` headers (non-breaking, any later time)
- any product feature or UI change beyond what a renamed endpoint forces
- Go code of any kind
- social login (`socialLogin` stays `x-status: target`)

## Story Order

| # | Story | Primary feature | State |
| --- | --- | --- | --- |
| 1 | [Lint Ruleset and Schema Hygiene](stories/01-lint-ruleset-and-schema-hygiene.md) | Platform | shipped (#56) |
| 2 | [Problem Details Error Model](stories/02-problem-details-error-model.md) | Platform | shipped (#57) |
| 3 | [Auth and Ownership Status Codes](stories/03-auth-and-ownership-status-codes.md) | Auth & Profile | shipped (#58) |
| 4 | [`/me` Consolidation](stories/04-me-consolidation.md) | Auth & Profile | shipped (#59) |
| 5 | [Naming Consistency](stories/05-naming-consistency.md) | Platform | shipped (#60) |
| 6 | [Workout Editor Sub-Resource and Concurrency](stories/06-workout-editor-sub-resource-and-concurrency.md) | Workout Logging | shipped (#62) |
| 7 | [Enrollment as a Resource](stories/07-enrollment-as-a-resource.md) | Programs | shipped (#64) |

States: `planned`, `in progress`, `shipped`, `deferred`. Update the row when a story's PR opens and when it merges; archive or delete the story file after it ships, per `planning/README.md`.

## Sequencing Rationale

- Story 1 lands the linter and the schema shapes every later story builds on (concrete page schema, `additionalProperties: false`), so each following PR is checked by Spectral from the start.
- Story 2 changes the one shape every client parses on every failure. It goes before the status-code story so 401/429 responses are born in the new envelope.
- Stories 3 and 4 are the auth surface; both clients' session code is touched once, together.
- Story 5 is the widest rename and is deliberately after the auth stories so the generated iOS client is regenerated for naming only once the error and auth shapes are stable.
- Story 6 depends on the `{workoutId}` parameter name from story 5 and on the 412/428 problems from story 2.
- Story 7 is independent and smallest; last so an unenroll affordance can be added without blocking anything.

## Success Metrics

- Redocly and Spectral (Zalando ruleset, no disabled rules left) both green on `contracts/openapi.yaml`
- Schemathesis green against the PHP backend with no new excluded operations
- web Vitest and build green; iOS `WorkoutsCore` `swift test` and `xcodebuild test` green on every story
- `info.version` is `2.0.0` and no response anywhere carries `message` at the top level, a dot-path error key, `pivot`, `password_confirmation`, or a capitalised weekday
- the Go backend epic's story 1 spike runs against the hardened contract

## Risks

- `swift-openapi-generator` and the `application/problem+json` media type: the generator supports non-JSON media types by name, but the new `Problem` and `ValidationProblem` schemas must stay free of `allOf` with nullable `$ref`s (the trap story 1 of the iOS epic documented). Mitigated by writing `ValidationProblem` as a flat object, not `allOf [Problem, …]`, and by compiling `WorkoutsAPI` in the same PR.
- The web refresh path keys on `response.data.code === 'token_expired'`; the iOS middleware on the same string. Mitigated by keeping `code` at the top level of `Problem` and by `apps/web/src/lib/api.test.js` and `AuthMiddlewareTests.swift` asserting it.
- `additionalProperties: false` on request bodies makes Schemathesis `negative_data_rejection` strict; any PHP form request that silently ignores unknown keys now has to reject them. Mitigated by a shared `RejectsUnknownKeys` rule applied in story 1 and the contract job in CI.
- Lowercasing `Weekday` touches the enum backing values, the `program_workout_template.weekday` column data, the catalog data files and the validator. Mitigated by one migration that rewrites the column in place and `CatalogSyncTest` fixtures updated in the same PR.
- Seven breaking PRs in a row on one SPA. Mitigated by the order above and by every PR deploying together (the web image and the API image are built from one tag).

## Outcome

What shipped differs from the plan in a few deliberate places, each recorded in the Decisions table or the story's PR:

- the `ETag` is the strong tag `"<id>-<version>"` from a new `workouts.version` counter, not a weak `updated_at` tag (decision 6)
- path parameters are renamed in the contract only; Laravel keeps its binding names (`{program}`), since the URL is identical (`docs/api-design.md`, Naming)
- `Activity` keeps a top-level `exercise_id` next to the nested `exercise` snapshot, matching the write shape
- Schemathesis found three older PHP gaps along the way, all fixed: query keys on `GET` were not rejected (story 1), body integers accepted `true` (story 5, now `integer:strict`), and a new user's `locale`/`theme_preference` serialized as `null` (story 7, model defaults)
- the iOS generated client percent-encodes header values; `EntityTagMiddleware` restores `If-Match` (story 6)

## Done Criteria

This epic is done when:

- all seven stories are shipped or explicitly deferred
- `.github/workflows/lint.yml` runs Spectral with the Zalando ruleset and no story-scoped rule is still disabled
- the contract, `docs/architecture.md` and `docs/features/*` describe Problem Details, the `{xxxId}` parameters, `/me`, `PUT …/activities` with `If-Match`, and `…/enrollment` as current state
- the web SPA and the iOS app register, log in, enroll, log a workout and change a password against the hardened API with no client-side special cases for the old shapes
- `planning/epics/go-backend/` story 1 is unblocked

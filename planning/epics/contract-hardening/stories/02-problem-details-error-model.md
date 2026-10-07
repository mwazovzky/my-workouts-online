# User Story: Problem Details Error Model

Replace the home-grown `{message, code}` envelope with RFC 9457 Problem Details, pointer-based validation errors and enumerated error codes, and echo the request id on every response.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Auth & Profile (client session code reads error codes), Workout Logging (editor form errors)
- Creates New Feature: No

## Problem Statement

Every non-2xx response is `{message, code}`, with 422 adding `errors: {"activities.0.sets.1.effort_value": [{code, message}]}`. Three things are wrong with it. It is not the standard every client library, gateway and linter understands (RFC 9457, `application/problem+json`), so each client hand-parses it. The validation map keyed by dot path generates as `additionalProperties`, which the Swift client reads as `value2.errors.additionalProperties`, and dot paths cannot express keys that contain dots. The per-field `code` is the snake-cased Laravel rule class (`confirmed`, `unique`, `heart_rate_zone_within_range`), which a second backend would have to imitate. The request id is logged by `LogContext` but never returned, so a user-reported error cannot be matched to a log line.

## User-Visible Outcome

None. Form errors appear under the same fields; failures read the same.

## Scope

- Contract: `Problem` `{type, title, status, detail, instance, code}` (all required except `instance`), served as `application/problem+json`; `ValidationProblem` as a flat object (not `allOf`) with `code: validation_failed` and `errors: [{pointer, code, detail}]`; `FieldErrorCode` enum `required`, `invalid_format`, `invalid_value`, `too_short`, `too_long`, `out_of_range`, `not_unique`, `not_found`, `mismatch`; `ErrorCode` enum listing every top-level code in use (`unauthenticated`, `token_expired`, `refresh_token_missing|invalid|expired|reused`, `email_unverified`, `forbidden`, `not_found`, `in_use`, `validation_failed`, `too_many_requests`, `invalid_verification_link`, `invalid_signature`, plus `bad_request`, `method_not_allowed`, `conflict`, `internal_error`). All `responses` components switch to the new schemas.
- PHP `app/Exceptions/Api/ApiErrorResponse.php` and `bootstrap/app.php`: build `Problem`; `type` = `{APP_URL}/problems/{code}`; `title` = stable English per code (a `ProblemTitle` map, not `__()`); `detail` = the localized message; `instance` = request path + `#` + request id; JSON Pointer built from Laravel's dot-path attribute (`activities.0.sets.1.effort_value` → `/activities/0/sets/1/effort_value`, with `~` and `/` escaped); a rule-name → `FieldErrorCode` map (`required`→`required`, `email|date|integer|…`→`invalid_format`, `in|enum|exists`→`invalid_value` or `not_found`, `min|max|between` by type → `too_short|too_long|out_of_range`, `unique`→`not_unique`, `confirmed|current_password`→`mismatch`, custom rules mapped explicitly).
- `X-Request-Id` response header on every API response (`LogContext` middleware already derives it).
- Web: `apps/web/src/composables/useApiForm.js` flattens `errors[]` by pointer to field names (`/email` → `email`, nested pointers kept as `activities.0.sets.1.effort_value` for the editor); `apps/web/src/lib/api.js` keeps `response.data.code === 'token_expired'`; pages that show `response.data.message` (`VerifyEmail.vue`, `VerifyEmailLink.vue`, `Admin/EquipmentIndex.vue`) read `detail`.
- iOS: `APIError.swift` decodes `Components.Schemas.Problem` / `ValidationProblem`; `AuthMiddleware.swift` reads `Problem.code`; `FormErrors.swift` maps pointers; fixtures `error-401.json`, `error-404.json`, `error-422.json` rewritten.
- `npm run api:types` regenerated.

## Non-Goals

- changing which status code any endpoint returns (story 3)
- localizing `title`
- a problem-type documentation site behind the `type` URLs (they are identifiers; the docs page can come later)

## Acceptance Criteria

- Redocly lint green; Spectral green with the Problem JSON rules now enabled
- `scripts/contract-test.sh` green on the dev stack
- PHP suite green; web `npm test` and `npm run build` green; iOS `swift test` in `WorkoutsCore` and `xcodebuild test` green
- `npm run api:types` regenerated
- every non-2xx response has `Content-Type: application/problem+json`, the six `Problem` fields, and an `X-Request-Id` header equal to the id inside `instance`
- a 422 from `PATCH /workouts/{id}/save` carries `errors[].pointer` starting with `/activities/`
- no response contains a top-level `message`
- web refresh-and-retry and iOS refresh-and-retry still fire on `token_expired`

## Initial Tasks

- [ ] contract schemas, responses, `ErrorCode` and `FieldErrorCode` enums
- [ ] `ApiErrorResponse` rewrite: title map, pointer builder, rule → code map, `instance`
- [ ] `X-Request-Id` response header
- [ ] web `useApiForm`, `api.js`, pages reading `message`
- [ ] iOS `APIError`, `AuthMiddleware`, `FormErrors`, fixtures
- [ ] regenerate web types; docs

## Test Cases

- `tests/Feature/Api/ErrorEnvelopeTest.php`: shape for 401, 403, 404, 409, 422, 429; `instance` and header match; pointer escaping; every Laravel rule used in the app maps to a `FieldErrorCode`
- `tests/Feature/Workout/WorkoutSaveTest.php`: nested pointers for set errors
- `apps/web/src/lib/api.test.js`: refresh on `code: token_expired` in a Problem body
- `apps/web/src/composables/useApiForm.test.js` (new): pointer flattening
- iOS `APIErrorTests.swift`, `AuthMiddlewareTests.swift`, `APIMappingTests.swift` with the new fixtures

## Docs To Update

- `docs/architecture.md` (error handling: Problem Details, code registries, request id)
- `docs/features/auth-and-profile.md`, `docs/features/workout-logging.md` (error examples)
- `docs/testing.md` (how to assert on problems)

# API Design Standard

The rules every change to `contracts/openapi.yaml` must follow: naming, envelope, request bodies, status codes, errors and versioning, with the linter that enforces each rule where one can.

Rules marked **Target (story N)** are decided but not yet in the contract; the story number refers to `planning/epics/contract-hardening/`. Until that story ships, the current behaviour described next to it stands.

## Principles

- **Contract first.** `contracts/openapi.yaml` is written before the backend, the SPA and the iOS client change; every client is generated from or checked against it. Enforced by: review.
- **Base rulebook.** The [Zalando RESTful API Guidelines](https://opensource.zalando.com/restful-api-guidelines/) are the default answer to any question this page does not answer. Deviations are listed at the end, each with a reason. Enforced by: Spectral (Zalando ruleset).
- **Three enforcement layers.** Mechanical rules are checked by Spectral and Redocly in CI; semantic rules (which status code, what an error code means, what counts as breaking) live on this page and are checked in review; the *why* behind a rule lives in the Decisions table of the epic that introduced it. A rule is written here even when a linter checks it, so the page is complete on its own.

## Lint setup

Two linters run on every push and pull request (`.github/workflows/lint.yml`, job `openapi-lint`):

```bash
npx --yes @redocly/cli@latest lint contracts/openapi.yaml --config contracts/redocly.yaml
npx --yes @stoplight/spectral-cli@latest lint contracts/openapi.yaml --ruleset contracts/.spectral.yaml --fail-severity warn
```

- `contracts/redocly.yaml` extends Redocly `recommended` (structure, valid examples).
- `contracts/.spectral.yaml` extends `spectral:oas` and the Zalando ruleset (the [Baloise ruleset](https://github.com/baloise-incubator/spectral-ruleset), pinned to a commit so CI is reproducible) and adds the `house-*` rules named on this page. Two Zalando rules are redefined there verbatim with a null guard (`must-use-standard-formats-for-date-and-time-properties-*`) because the upstream JSONPath crashes on the `null` entries of nullable enums.
- A warning fails the build (`--fail-severity warn`); there is no "advisory" level.
- Error responses are checked by the Zalando Problem JSON rules `must-use-problem-json-for-errors` (every 4xx/5xx is `application/problem+json`) and `must-use-valid-problem-json-schema` (the schema has `type` as a URI, `title`, `status` as `int32`, `detail`, `instance`).
- Every 2xx JSON response carries an example, at content level or on its (`data`) schema; Redocly validates examples against their schema. Enforced by: Spectral `house-response-examples`, Redocly `no-invalid-media-type-examples`.
- Path-specific exceptions use Spectral `overrides` (today only `/.well-known/jwks.json`, whose segments RFC 8615 fixes), never a rule-wide exception.
- **Disabling a rule.** A rule may be turned off only with a comment on that line, of one of two kinds: a permanent house deviation, stating the reason and matching a row in [Deviations from Zalando](#deviations-from-zalando); or `TODO(contract-hardening/NN)` naming the story that will turn it back on. A disabled rule without a comment is a review blocker. Enforced by: review.

## Naming

| Rule | Current | Enforced by |
| --- | --- | --- |
| JSON property names are ASCII `snake_case` | `workout_template_id`, `is_enrolled` | Spectral `must-use-snake-case-for-property-names` |
| Query parameters are `snake_case` | `page`, `per_page` | Spectral `must-use-snake-case-for-query-parameters` |
| Path segments are lowercase, plural nouns, hyphen-separated | `/workout-templates`, `/admin/equipment` | Spectral `must-use-lowercase-with-hypens-for-path-segements` |
| `operationId` is lowerCamelCase, verb first | `listWorkouts`, `adminCreateEquipment` | Spectral `house-operation-id-camel-case` |
| Enum values are lowercase `snake_case` | `in_progress`, `heart_rate_zone`, `monday` | Spectral `house-enum-lowercase-snake-case` |
| Path parameters are lowerCamelCase; an integer identifier is `{resourceId}`, never the backend's binding name | `{programId}`, `{workoutId}`, `{workoutTemplateId}` | Spectral `house-path-parameter-names`, `house-path-parameter-ids` |
| Timestamps end in `_at` and are `date-time`; a state that has a moment is a nullable timestamp, not a boolean | `created_at`, `email_verified_at` | review |
| Header parameters are `Hyphenated-Pascal-Case` | `Accept-Language`, `X-Requested-With` | Spectral `should-use-hyphenated-pascal-case-for-header-parameters` |
| Action sub-resources are verbs only for state transitions | `/workouts/{workoutId}/complete`, `/repeat` | review |
| A relationship the caller switches on and off is a singular noun sub-resource with idempotent `PUT` / `DELETE`, not an action | `/programs/{programId}/enrollment` | review |
| No storage or framework names in the wire format: a join-table attribute is named for what it means | `WorkoutTemplate.assignment.weekday`, not `pivot` | review |
| Related data embedded for display is one nested snapshot object next to the foreign key, not a row of prefixed fields | `Activity.exercise_id` + `Activity.exercise {name, equipment_name, …}` | review |

The path parameter name is a contract concern only: the URL is the same whatever the name, so a backend's router may bind it under its own name (Laravel binds `{programId}` as `{program}`).

## Envelope and pagination

- Every 2xx JSON response wraps its payload in a top-level `data` object or array. Exceptions: `/.well-known/jwks.json` (`{ keys }`, a standard document), `/health*`, and the `/api/v1/auth/*` token endpoints, which keep the OAuth 2.0 token response shape (`access_token`, `token_type`, `expires_in`; RFC 6749). Enforced by: Spectral `house-data-envelope`.
- A paginated list is a named page schema per resource (`WorkoutPage` = `data: Workout[]` + `links: PageLinks` + `meta: PageMeta`), never a generic `allOf` wrapper, so generated clients get typed items. Enforced by: review.
- `PageLinks` has exactly `first`, `last`, `prev`, `next` (absolute URLs or `null`). `PageMeta` has exactly `current_page`, `from`, `last_page`, `per_page`, `to`, `total`. Nothing else is sent. Enforced by: Spectral `oas3-schema` (`additionalProperties: false` on both) and `scripts/contract-test.sh`.
- Page selection is `page` (integer ≥ 1, default 1) and `per_page` (integer 1–100, default 20). Out-of-range values are a 422. Enforced by: Spectral `oas3-schema` (parameter bounds) and the backend tests.
- `GET /api/v1/workouts` is the only paginated list. Catalog lists (`/programs`, `/exercises`, the admin lists) return the full set under `data`; a list gains pagination when it needs it, using the same page schema pattern. Enforced by: review.
- No filter or sort parameters exist. When one is added, the convention is `?sort=-updated_at` (leading `-` for descending) and `?filter[field]=value`. Enforced by: review.

## Request bodies

- Every request body object, nested objects included, declares `additionalProperties: false`. The backend rejects any undeclared key with 422, field code `unknown_field`, detail "This field is not allowed."; nested keys are reported with their full pointer. Enforced by: Spectral `house-strict-request-bodies`, `scripts/contract-test.sh` (`negative_data_rejection`), `App\Http\Requests\ApiFormRequest`.
- The same applies to the query string: an operation that declares query parameters (`listWorkouts`) rejects undeclared ones with 422. Operations without query parameters ignore the query string. Enforced by: `scripts/contract-test.sh`, `ApiFormRequest`.
- Read and write shapes are separate schemas (`Exercise` vs `ExerciseWrite`); `readOnly`/`writeOnly` are not used. Enforced by: review.
- `PUT` is full replacement: the write schema lists every settable field and the server replaces the whole resource (admin updates, template activities, `PUT /workouts/{workoutId}/activities`). A collection that is only ever written as a whole is a sub-resource replaced with `PUT`, never a verb in the path. Enforced by: review.
- `PATCH` bodies are RFC 7386 merge patches: every field optional, absent fields untouched, at least one field present (`minProperties: 1`), no `required` list (`PATCH /me` with `MePatch`). Enforced by: Spectral `house-patch-merge-patch`.
- A resource has one name: the current user is `/me` for reads and writes (`PATCH /me`, `PUT /me/password`, `DELETE /me`), never a parallel `/profile`. Enforced by: review.
- Confirmation fields (`password_confirmation`) are a client-side concern and are not part of any request body. Enforced by: `house-strict-request-bodies` (the key is undeclared, so the server rejects it).
- Optional object fields are omitted from responses rather than sent as `null`, and nullable enums are inlined (`type: [string, 'null']` with the values), because code generators drop a nullable `$ref`. Enforced by: review.
- Nullability is written as `type: [X, 'null']` (OpenAPI 3.1), never `nullable: true`. Enforced by: Spectral `oas3-schema`.
- A field the server always sends is `required` and not nullable (`Workout.created_at`); `null` is reserved for a value that is genuinely absent. Enforced by: review.
- Responses carry data, not presentation: no server-rendered labels (`status_label`, `weekday_label`, unit headers). Enums are sent as values and each client maps them to text through its own string catalog, as the API-first decision on client-owned i18n requires. User-entered and catalog content (`name`, `description`) is still localized by `Accept-Language`. Enforced by: review.

## HTTP methods and status codes

| Situation | Status | Rule |
| --- | --- | --- |
| Read, update, or action that returns the resource | 200 | Body is the `data` envelope |
| Resource created (`POST /workouts`, admin creates, `repeat`) | 201 | Body is the created resource |
| Accepted for later processing (forgot password, resend verification) | 202 | Empty body; identical response whether or not the account exists |
| Done, nothing to return (delete, enroll, unenroll, logout, password change) | 204 | Empty body |
| Missing, malformed or expired access token | 401 | `code` is `unauthenticated` or `token_expired`; only `token_expired` triggers a client refresh |
| Authenticated but not allowed by role or verification state | 403 | `forbidden`, `email_unverified` |
| Resource does not exist | 404 | `not_found`; `detail` is always "Record not found." and never reveals whether the id exists for someone else |
| Caller does not own the resource | 404 on every method | Ownership never leaks; 403 is reserved for the caller's own resource in a state that forbids the action (saving a completed workout) |
| Operation refused by a reference guard (equipment in use, template assigned) | 409 | `code` is `in_use` |
| Well-formed input that fails validation | 422 | `validation_failed` with `errors[]` (see [Errors](#errors)); never used for authentication outcomes |
| Bad credentials at login | 401 | `invalid_credentials`; the `Problem.detail` is the message to show. 422 on login is only malformed input |
| Login lockout (5 failures per email and IP within a minute) | 429 | `too_many_attempts` with `Retry-After` in seconds |
| Per-route throttle exceeded | 429 | `too_many_requests`; always carries `Retry-After` |
| Write rejected because the client's copy is stale | 412 | `precondition_failed`; nothing is written, the client reloads |
| Conditional write sent without `If-Match` | 428 | `precondition_required` |

Only standard status codes are used (Spectral `must-use-standard-http-status-codes`, redefined in the house ruleset with 422 added to Zalando's list); which one applies is decided by the table above, not by Zalando's method-to-status table (see [Deviations](#deviations-from-zalando)). Enforced by: review.

## Concurrency

- Every single-workout response (`GET`, create, replace activities, complete, repeat) carries `ETag: "<id>-<version>"`. `version` is a counter on the workout bumped by every change to it or its activities, so the tag changes even within one second (an `updated_at` tag would not). Lists and admin resources carry no tag. Enforced by: Schemathesis (`ETag` is a required response header), backend tests.
- `PUT /workouts/{workoutId}/activities` requires `If-Match`. The tag is compared strongly (RFC 9110): a weak `W/"…"` tag never matches, a comma-separated list matches if any entry does, and `*` matches any existing workout. Missing → 428 `precondition_required`; no match → 412 `precondition_failed` with nothing written. The comparison runs against the row locked for the write, so two concurrent saves with the same tag cannot both succeed. Enforced by: backend tests.
- Ownership (404) and state (403) are checked before the precondition, so a 412 never reveals anything about a workout the caller cannot write.
- The tag identifies the workout's version, not the bytes of one localized representation; the same version read in `en` and `ru` has the same tag. Clients send back the tag of the copy they edited. Enforced by: review.
- Other writes on a workout (complete, repeat, delete) are not conditional. Enforced by: review.

## Errors

Every non-2xx response under `/api/*` is an [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457) document, served as `Content-Type: application/problem+json`. Enforced by: Spectral `must-use-problem-json-for-errors`, `must-use-valid-problem-json-schema`, `scripts/contract-test.sh` (schema of every documented error response).

```json
{
  "type": "https://my-workouts.online/problems/validation_failed",
  "title": "Validation failed",
  "status": 422,
  "detail": "Проверьте введённые данные.",
  "instance": "/api/v1/workouts/42/activities#0f1e9a3c-7b1d-4e7a-9d6b-2c3f4a5b6c7d",
  "code": "validation_failed",
  "errors": [
    { "pointer": "/activities/0/sets/1/effort_value", "code": "out_of_range", "detail": "Значение усилия должно быть не больше 100000." }
  ]
}
```

| Field | Meaning |
| --- | --- |
| `type` | `{APP_URL}/problems/{code}`: an identifier for the problem class. Nothing is served at that URL yet |
| `title` | Stable English summary of the `code`; never localized, never shown to users |
| `status` | The HTTP status, repeated in the body |
| `detail` | Localized, human-readable; what a client shows when it has nothing better |
| `instance` | Request path + `#` + request id; the same id is in the `X-Request-Id` response header and in every log line of that request |
| `code` | A value of the contract's `ErrorCode` enum; the field clients switch on |

- `ErrorCode` (contract enum, mirrored by `App\Enums\ErrorCode` with a title per value): `bad_request`, `unauthenticated`, `invalid_credentials`, `token_expired`, `refresh_token_missing`, `refresh_token_invalid`, `refresh_token_expired`, `refresh_token_reused`, `forbidden`, `email_unverified`, `not_found`, `method_not_allowed`, `conflict`, `in_use`, `validation_failed`, `too_many_requests`, `too_many_attempts`, `invalid_verification_link`, `invalid_signature`, `internal_error`. The backend may not emit a code outside the enum. Enforced by: `scripts/contract-test.sh` (the enum is in every error schema).
- A 422 is a `ValidationProblem`: the same six fields with `code: validation_failed` plus `errors`, a list of `{ pointer, code, detail }`. `pointer` is an RFC 6901 JSON Pointer into the request body (`/activities/0/sets/1/effort_value`; `~` and `/` in a key are escaped as `~0` and `~1`); `detail` is localized. One entry per failed rule, so a field can appear more than once.
- `errors[].code` is a value of the `FieldErrorCode` enum (mirrored by `App\Enums\FieldErrorCode`): `required`, `invalid_format`, `invalid_value`, `too_short`, `too_long`, `out_of_range`, `not_unique`, `not_found`, `mismatch`, `unknown_field`. Validation rules map to it in `FieldErrorCode::fromRule`: `required` → `required`; type and format rules (`email`, `integer`, `numeric`, `boolean`, `string`, `array`, `date`, …) → `invalid_format`; `in`, `enum` → `invalid_value`; `exists` → `not_found`; `min`/`max`/`between` → `too_short`/`too_long` on strings and arrays, `out_of_range` on numbers; `unique`, `distinct` → `not_unique`; `confirmed`, `current_password` → `mismatch`; an undeclared key → `unknown_field`; custom rules are mapped one by one. Enforced by: `tests/Feature/Api/ErrorEnvelopeTest.php` (every rule the app uses maps to a code).
- Every API response, success or error, carries `X-Request-Id`: the incoming header when it is a UUID, otherwise a generated one. Enforced by: `tests/Feature/Api/ErrorEnvelopeTest.php`.
- Clients switch on `code`, never on `title` or `detail`: the web `api.js` refreshes on `token_expired`, `useApiForm` keys form errors by the pointer flattened to a dotted path (`activities.0.sets.1.effort_value`), and pages show `detail`; iOS `APIError` decodes `Problem`/`ValidationProblem`, `AuthMiddleware` reads `Problem.code`, `FormErrors` maps pointers to fields. Enforced by: `apps/web/src/lib/api.test.js`, `apps/web/src/composables/useApiForm.test.js`, iOS `AuthMiddlewareTests`, `APIErrorTests`.
- Where it is built: `App\Exceptions\Api\ApiErrorResponse` (`problem()`, `fromHttpException()`, `fromValidation()`, `fromThrowable()` for 500s), wired in `bootstrap/app.php`. An explicit code is thrown as `ApiException(status, ErrorCode, detail)`.
- There is no `default` response in the contract: every error status an operation can return is listed (see [Deviations](#deviations-from-zalando)). Enforced by: review.

**Adding an error code.** Add it to the `ErrorCode` enum in the contract and to the `examples` of every response that can return it; add the case and its title to `App\Enums\ErrorCode`; throw it via `ApiException` or map it in `ApiErrorResponse`; cover it in `tests/Feature/Api/ErrorEnvelopeTest.php`. A field code follows the same path through `FieldErrorCode`. Codes are never renamed; a code a client switches on (`token_expired`, `validation_failed`, the `refresh_token_*` family) is part of the compatibility promise. Enforced by: review.

## Versioning

- `info.version` is the semantic version of the contract (Spectral `must-use-semantic-versioning`). A breaking change bumps the major version; an additive change bumps the minor.
- The path stays `/api/v1`. The `v1` segment is a routing prefix, not a version strategy: a second path version would be introduced only to run two incompatible contracts side by side for clients that cannot be updated together, which does not apply while every client lives in this repository. Enforced by: review.
- Breaking: removing or renaming a field, path, parameter or enum value; changing a type or nullability; adding a `required` request field; changing a status code or error code. Not breaking: a new optional field, a new operation, a new enum value a client is told to tolerate, a new `examples` entry.
- Every breaking change ships in one pull request with the backend, the SPA and the iOS client, so no commit on `main` has a client and a contract that disagree. Enforced by: review and CI (`contract.yml`, `tests.yml`, `ios.yml`).

## Deviations from Zalando

Rules of the Zalando ruleset that are off permanently in `contracts/.spectral.yaml`, with the reason recorded there as a comment.

| Rule | Reason |
| --- | --- |
| `must-not-use-uri-versioning`, `should-not-use-api-as-base-path` | The path is `/api/v1` because nginx routes `/api/*` to the API upstream on the same origin as the SPA; the semantic version lives in `info.version` (see [Versioning](#versioning)) |
| `should-use-x-extensible-enum`, `should-declare-enum-values-using-upper-snake-case-format` | Enums are closed `enum`s in lowercase `snake_case`: the clients generate exhaustive enums from them, and the backend, SPA and iOS enums share the same backing values |
| `must-define-a-format-for-integer-types`, `must-define-a-format-for-number-types` | Integers are left unformatted so generated clients use their native `Int` / `number`; `difficulty_value` is a plain `number` with one decimal |
| `must-have-info-x-api-id`, `must-provide-api-identifiers`, `must-provide-api-audience`, `must-have-info-contact-email` | Zalando-internal registry metadata; `info.contact` carries a name and a URL only |
| `should-limit-number-of-resource-types`, `should-limit-number-of-sub-resource-levels` | The `/admin` namespace mirrors the catalog resources with an admin representation, doubling the count by design, and the `/api/v1` prefix adds two segments to every path |
| `should-use-well-understood-http-status-codes` | Zalando's method-to-status table has no 422 and no 204 for `POST`; the house table above uses 422 for validation and 204 for a `POST` action with nothing to return. `must-use-standard-http-status-codes` is kept, redefined with 422 |
| `must-specify-default-response`, `must-use-problem-json-as-default-response` | A documented `default` response makes every status "documented", which silences the undocumented-status check of the contract suite (it caught a real gap in story 1). Every error status an operation can return is listed explicitly instead, all as `application/problem+json` |

## Checklist for a contract change

1. Edit `contracts/openapi.yaml`; add or update `examples` for every touched response.
2. Lint: both commands in [Lint setup](#lint-setup) pass with no new disabled rules.
3. Backend: implement, run `vendor/bin/pint --dirty` and `php artisan test --compact`.
4. Contract test: `scripts/contract-test.sh http://localhost:8080` against the dev stack, then reseed.
5. Web: `npm run api:types`, then `npm run lint:fix && npm run format && npm test && npm run build`.
6. iOS: build `apps/ios/Packages/WorkoutsAPI` (the generator is the drift check) and run `swift test` in `WorkoutsCore`.
7. Docs: this page if a rule changed; `docs/pages-and-routes.md` for a path or parameter; the owning feature doc for behaviour; `docs/architecture.md` for a cross-cutting pattern.

# 0006. Problem Details, Locale and Request Validation in the HTTP Platform

- Status: accepted
- Date: 2026-10-11
- Story: [Go epic story 03](../../../../planning/epics/go-backend/stories/03-shared-kernel-http-platform-and-meta.md)

## Context

Every non-2xx response under `/api/*` is an RFC 9457 Problem (`type`, `title`, `status`, `detail`, `instance`, `code`), served as `application/problem+json`. Validation failures add `errors: [{pointer, code, detail}]` with the contract's `FieldErrorCode` values. `detail` is localized from `Accept-Language`, and `title` is stable English. The PHP API builds these in `ApiErrorResponse`, maps Laravel rule names to field codes in `FieldErrorCode::fromRule`, and takes messages from Laravel's lang files.

In the Go service, handlers are generated (ADR 0003): the strict server decodes requests and encodes responses, and a handler returns `(response, error)`. Something has to:

- turn those errors into Problems;
- reject requests the contract forbids before a handler sees them (wrong types, unknown fields, out-of-range parameters, malformed JSON);
- answer operations later stories have not implemented yet;
- give unmatched paths and methods Problem bodies instead of `net/http`'s plain text.

## Considered Options

1. Hand-written validation per handler only: every handler checks types and unknown fields itself.
2. Validate every request against the embedded contract with kin-openapi's `openapi3filter` before the handler, and keep business rules (uniqueness, cross-field checks, ownership) in use cases with `kernel/validate`.
3. A JSON Schema library over the contract's schemas, outside kin-openapi.

## Decision

Option 2, with these parts:

- **Errors.** `kernel/apperr` holds one `Error` type. Its `Kind` gives the status: NotFound 404, Conflict 409, Validation 422, Unauthenticated 401, Forbidden 403, RateLimited 429 with `Retry-After`, PreconditionFailed 412, PreconditionRequired 428, BadRequest 400. Its `Code` is the contract's `ErrorCode`. Its `Detail` is an English message key, and `Args` fill its placeholders. `httpapi.Responder` is the only place that maps them. It sets the `type` (`{APP_URL}/problems/{code}`), the `title` (a table, one per code), the `instance` (path `#` request id) and the localized `detail`. Any other error is a 500 `internal_error` with detail "Server Error", and the cause is logged, never sent. A test keeps `apperr`'s code lists equal to the contract's `ErrorCode` and `FieldErrorCode` enums.
- **Messages.** `kernel/i18n` embeds `locales/{en,ru}.json`, ported from the PHP lang files: Laravel's validation, auth and passwords files flattened to `validation.required`-style keys, plus the English sentences the backend translates, keyed by themselves. Placeholders follow Laravel (`:attribute`, `:Attribute`, `:ATTRIBUTE`). A field's display name is its name with underscores as spaces, as in Laravel, and is itself translated so a catalog key can name a field. A test requires both catalogs to have the same keys and placeholders, and a translation for every message.
- **Locale.** The base language of the highest-quality `Accept-Language` entry that is supported wins (`ru-RU` matches `ru`); otherwise `en`. Every response gets `Content-Language` and `Vary: Accept-Language`.
- **Request validation.** A per-operation middleware inside the mux runs `openapi3filter.ValidateRequest` against the embedded contract. Failures become field errors:
  - the pointer comes from the schema error's path; for a query parameter it is `/name`, and for malformed JSON it is the empty pointer (the whole body);
  - the field code comes from the failed keyword: `required` is required, `type`, `format` and `pattern` are invalid_format, `enum` is invalid_value, `minLength` and `minItems` are too_short, `maxLength` and `maxItems` are too_long (Laravel's `min`/`max` on an array is a length bound; corrected in story 8), `minimum` and `maximum` are out_of_range, and `additionalProperties` is unknown_field.

  kin-openapi validates a 3.1 document with a JSON Schema 2020 validator whenever a schema compiles on its own, and that validator reports failures as English text without the keyword. The validator is therefore given a copy of the spec labelled 3.0, so kin's own validator runs. It names the keyword and accepts `type: [X, 'null']`, the only 3.1 construct the contract uses. `TestNullableFields` guards that.
- **Composition.** The generated `StrictServerInterface` is implemented by one struct in `bootstrap` that embeds each module's handler type plus `unimplemented`, whose stubs return `httpapi.ErrNotImplemented` (501, `internal_error`). All embeds sit at the same depth. Implementing an operation in a module without deleting its stub makes the method ambiguous, and the struct stops compiling, so the stub list cannot go stale. The same rule caught a real clash in this story: `health.Readiness.Register` against the `register` operation, renamed `AddCheck`.
- **Routing.** The `ServeMux` decides between 404 and 405 (and computes `Allow`), and its plain-text body is replaced by a Problem. A path parameter the generated server cannot bind (`/workouts/abc`) is a 404, as PHP constrains every id route to digits. A malformed query parameter is a 422.

Option 1 repeats the contract in Go code and drifts from it. Option 3 adds a second schema stack next to the one oapi-codegen already uses (kin-openapi).

Departures from the PHP API, all deliberate:

| Case | PHP | Go |
| --- | --- | --- |
| Malformed JSON body | Treated as an empty body: one `required` error per required field | One `invalid_format` error with the empty pointer, detail "The request body must be valid JSON." |
| `detail` of 403, 404, 429, 500 | English for every locale | Localized ("Запись не найдена.") |
| "(and N more errors)" suffix | English for every locale | Localized |
| Order of validation errors | Rule declaration order | Field name order (kin-openapi's) |
| 405 `detail` | Names the route (`api/v1/meta`) | "The DELETE method is not supported for this route. Supported methods: GET, HEAD." |
| Global `API_RATE_LIMIT_PER_MINUTE` | Defined but never attached | Enforced per client IP under `/api/`, with `X-RateLimit-Limit` and `X-RateLimit-Remaining` |
| `Content-Language` on an unmatched route | Absent | Present |
| "An English name is required." in Russian | Untranslated | "Требуется название на английском." |

Aligned in [story 15](../../../../planning/epics/go-backend/stories/15-php-parity-fixes.md): PHP now localizes the 403, 404, 429 and 500 `detail` and the "(and N more errors)" suffix, translates "An English name is required.", and enforces `API_RATE_LIMIT_PER_MINUTE` per client IP on every `/api` route. The rows above record PHP before that story.

## Consequences

- Good: a handler never sees a request whose shape contradicts the contract, and the 422 body for a shape error needs no code in the handler. Even an operation that is not implemented yet answers an invalid request with the right 422.
- Good: the contract and the code cannot disagree on error or field codes without a failing test.
- Bad: kin-openapi's validator, not the handler, decides some messages, so a few Go messages differ from PHP's in wording or order (table above). Accepted; clients switch on `code` and `pointer`, never on `detail`.
- Bad: the 3.0 label is a workaround. If the contract adopts a 3.1 keyword kin's own validator ignores (`const`, `prefixItems`, `unevaluatedProperties`), validation silently loses it. The Spectral ruleset or `TestNullableFields` should be extended when that happens.
- Done in story 8: an operation that declares query parameters rejects undeclared ones as `unknown_field` (kin-openapi does not), as PHP's `listWorkouts` does; operations without query parameters ignore the query string, as their PHP routes do. Also in story 8: a `null` where the contract allows none, and an empty array failing `minItems`, are `required`, as Laravel treats both as missing; and `httpapi.Options.FieldMessages` gives an operation the attribute names and messages its PHP form request declares (`attributes()`, `messages()`), keyed by pointer pattern (`/activities/*/order`). Story 10: a nested field's display name keeps its underscores (`category_ids.1`), as Laravel shows it.
- Follow-up: the rate limiter keys on the TCP peer. Story 13 must trust `X-Forwarded-For` from nginx only, or every client behind the proxy shares one limit.

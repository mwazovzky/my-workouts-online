# User Story: Lint Ruleset and Schema Hygiene

Put a public REST rulebook in CI and tighten the schemas every later story builds on: strict request bodies, examples, concrete pagination, health endpoints in the contract.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Workout Logging (`listWorkouts` pagination only)
- Creates New Feature: No

## Problem Statement

`lint.yml` runs only Redocly's `recommended` set, which checks structure, not API design. Request bodies allow unknown keys, so a client typo (`exercice_id`) is silently ignored by PHP and never caught by Schemathesis. `Paginated` is a generic `allOf` with `items: {}`, which every generator turns into an untyped array. `listWorkouts` has no `per_page`. `WorkoutResource` emits Laravel's `meta.links[]` and `meta.path`, which the contract does not describe. `/health` and `/health/ready` are relied on by nginx, CI and the deploy script but are not in the contract.

## User-Visible Outcome

None. Responses are byte-identical except that `listWorkouts` honours `?per_page=` and no longer sends `meta.links` and `meta.path`.

## Scope

- `contracts/.spectral.yaml` extending `@stoplight/spectral-rulesets` plus the Zalando ruleset (`spectral-ruleset-zalando` or the equivalent current package); a `TODO(contract-hardening/NN)` comment on every rule disabled because a later story fixes it (naming, Problem JSON, PATCH semantics). `.github/workflows/lint.yml` gains a `npx @stoplight/spectral-cli lint contracts/openapi.yaml` step.
- `additionalProperties: false` on every request body schema (`RegisterRequest`, `LoginRequest`, the inline auth bodies, `WorkoutSaveRequest`, `SetWrite`, all `*Write` admin schemas, `TranslationsWrite`, `NameTranslationsWrite`). PHP: a shared `RejectsUnknownKeys` trait or `prohibited` rules on each form request under `app/Http/Requests/` so unknown keys fail with 422.
- `examples` on every 2xx response that has none (the admin lists, `getMeta`, `getDashboard`).
- `/health` and `/health/ready` added under a `Health` tag with `security: []` and the JSON shapes from `routes/web.php`; excluded from the iOS generator's tag filter.
- `Paginated` replaced by `WorkoutPage` (`data: Workout[]`, `links: PageLinks`, `meta: PageMeta`) with named `PageLinks`/`PageMeta` schemas reusable by future lists; `meta` limited to `current_page`, `from`, `last_page`, `per_page`, `to`, `total`, with `additionalProperties: false`. `WorkoutResource`/`WorkoutController@index` stop emitting `meta.links` and `meta.path`.
- `per_page` query parameter on `listWorkouts` (integer, default 20, min 1, max 100) validated in the controller or a `WorkoutIndexRequest`.
- `npm run api:types` regenerated.
- `docs/api-design.md` (new): the house API standard that the linter only partly enforces. Zalando as the base ruleset, then every house rule on one page: snake_case fields, the `{data}` envelope, path-parameter naming, enum casing, the status-code decision table (401/403/404/409/412/422/428/429), PATCH as merge patch, pagination shape, `ETag` rules, versioning policy (`info.version` vs the path), and how to register a new error code. Each rule states whether Spectral checks it or a reviewer must. Sections for rules that later stories introduce are written now as "target" and marked current when that story ships. `CLAUDE.md` gets one line pointing to it next to `docs/code-style.md`; `docs/documentation-standard.md` gets a row for its role.
- Every disabled or customised rule in `contracts/.spectral.yaml` carries a comment naming the story or the `docs/api-design.md` section that justifies it, so the config reads as a rulebook rather than a wall of switches.

## Non-Goals

- enabling rules that the error, naming or auth stories will satisfy (they are disabled with TODOs here)
- pagination on any list other than workouts
- cursor pagination

## Acceptance Criteria

- Redocly lint green; Spectral green with the story-scoped rules disabled and documented
- `scripts/contract-test.sh` green on the dev stack, including `negative_data_rejection` for bodies with an unknown key
- PHP suite green; web `npm test` and `npm run build` green; iOS `swift test` in `WorkoutsCore` and `xcodebuild test` green
- `npm run api:types` regenerated; `WorkoutPage` appears as a named type
- `GET /api/v1/workouts?per_page=5` returns five items and `meta.per_page: 5`; `per_page=101` returns 422
- `GET /api/v1/workouts` response has no `meta.links` or `meta.path`
- `curl /health` and `/health/ready` match the contract schemas
- `docs/api-design.md` exists, every rule names its enforcement (Spectral rule id or "review"), and every rule disabled in `contracts/.spectral.yaml` is cross-referenced from it

## Initial Tasks

- [ ] add `contracts/.spectral.yaml`, run it locally, record every failing rule as either fixed here or disabled with a story TODO
- [ ] `lint.yml` step
- [ ] `additionalProperties: false` across request schemas; PHP unknown-key rejection
- [ ] `WorkoutPage`, `PageLinks`, `PageMeta`; drop the extra `meta` keys in PHP
- [ ] `per_page` parameter and validation
- [ ] `/health` paths in the contract; iOS generator tag filter unchanged (Health not listed)
- [ ] response examples
- [ ] `docs/api-design.md`, `CLAUDE.md` pointer, `docs/documentation-standard.md` row, rule comments in `.spectral.yaml`
- [ ] regenerate web types; docs

## Test Cases

- `tests/Feature/Api/WorkoutApiTest.php`: `per_page` default, custom, over-max; `meta` has exactly the six keys
- `tests/Feature/Api/ErrorEnvelopeTest.php`: an unknown body key on `POST /workouts` returns 422 with that key named
- `tests/Feature/HealthCheckTest.php`: unchanged, now asserted against the contract shapes
- `apps/web/src/pages/WorkoutIndex.vue` tests (add if missing): pagination controls read `meta.last_page`
- iOS `WorkoutListAndDetailTests.swift` with an updated `workouts-page.json` fixture

## Docs To Update

- `docs/api-design.md` (new: the house API standard)
- `docs/documentation-standard.md` (document role for the API standard)
- `CLAUDE.md` (pointer to `docs/api-design.md`)
- `docs/architecture.md` (contract section: Spectral ruleset, strict request bodies, named page schemas)
- `docs/features/workout-logging.md` (history pagination: `per_page`)
- `docs/testing.md` (lint job description)

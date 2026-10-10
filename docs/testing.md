# Testing

Practical testing guide for this project. Use this file for test-writing conventions, suite structure, and common patterns. Use [Code Style](code-style.md) for broader engineering standards.

## Goals

- Protect current behavior with the smallest useful test.
- Prefer feature tests for user-visible behavior and route-level rules.
- Use unit tests for isolated business logic, resources, enums, query builders, and services.
- Keep tests readable enough that they double as behavior examples.

## Commands

Run the smallest test scope that proves the change:

```bash
php artisan test --compact --filter=testMethodName
php artisan test --compact tests/Feature/Workout/WorkoutStoreTest.php
php artisan test --compact tests/Unit/Services/Workout/WorkoutServiceTest.php
npm test                 # in apps/web
npm run test:coverage    # in apps/web
php artisan test --compact
```

Backend commands run in `services/api-php` (inside the `app` container that is `/var/www/html`, so `docker compose exec -T app php artisan test --compact`). Container env values are real environment variables; `phpunit.xml` forces its own values (`force="true"`) and `tests/bootstrap.php` mirrors them into `$_SERVER`, which Laravel reads first, so the suite always uses SQLite in memory and never touches the MySQL dev database.

SPA tests run in `apps/web` (`docker compose exec -T node npm test`).

## Suite Structure

- `tests/Feature/` covers the full HTTP stack, page rendering, authorization, validation, and database-side outcomes.
- `tests/Unit/` covers isolated logic such as services, resources, enums, models, and query builders.
- `apps/web/src/**/*.test.js` covers SPA utilities, composables, stores, guards, and component behavior with Vitest.
- `tests/Unit/Catalog/CatalogDataIntegrityTest.php` validates the shipped `database/catalog/` data files; it fails on any broken slug, reference, or missing translation. Sync behavior is tested against the small catalog in `tests/Fixtures/catalog/`.
- `tests/Feature/Migrations/` covers data migrations. These tests use `DatabaseMigrations` instead of `RefreshDatabase`, load the migration file, and call its `up()` / `down()` against rows inserted in the old shape.
- `tests/TestCase.php` provides shared setup. The suite disables Vite and CSRF in tests, so feature tests can focus on application behavior.

## Frontend Tests

Frontend tests are for client-side logic that backend feature tests do not fully protect.

Use frontend tests for:

- Composables with branching or transformation logic
- Formatting and mapping utilities
- Components with meaningful client-side interaction, emitted events, or conditional rendering

Do not use frontend tests as a substitute for PHP feature coverage of routes, policies, validation, redirects, or persistence.

Current frontend toolchain:

- Vitest
- Vue Test Utils
- Happy DOM

Frontend coverage is generated with Vitest's V8 coverage provider and written to `apps/web/coverage/`.

First-reference examples in this repo:

- [Auth store](../apps/web/src/stores/auth.test.js)
- [API client](../apps/web/src/lib/api.test.js)
- [Program card component](../apps/web/src/components/ProgramCard.test.js)

Keep frontend tests focused:

- Prefer one component or module per file.
- Assert the behavior that matters, not implementation details.
- Stub child components when the parent behavior is the thing being tested.
- Use `apps/web/src/test/utils.js` (`withPinia`, `RouterLinkStub`) and mock `vue-router` when a component navigates.

## Feature Tests

Use a feature test when the change affects any of the following:

- A page, route, controller, form request, policy, middleware, or redirect
- Validation behavior or error messages
- API response shapes or error envelopes
- Database changes triggered through an HTTP request

Common patterns in this project:

- Use `RefreshDatabase`.
- Create data with factories.
- Authenticate with `actingAs($user)`.
- Hit named routes with `route(...)` instead of hard-coded URLs.
- Assert both the HTTP response and the persisted database state.
- API errors are RFC 9457 problems; assert them with the `AssertsProblems` trait (`tests/Concerns/`, used by `tests/TestCase.php`) rather than `assertJsonValidationErrors`, which expects Laravel's `errors` map:

  ```php
  $this->assertProblem($response, 404, 'not_found');
  $this->assertFieldErrors($response, ['activities.0.sets.1.effort_value'], 'out_of_range');
  ```

  `assertProblem` checks the status, the `application/problem+json` content type, the six problem fields and `code`; `assertFieldErrors` accepts dotted paths or JSON pointers and an optional `FieldErrorCode` value. API content translation is driven by `withHeader('Accept-Language', ...)`, not the user's stored locale.

Representative examples:

- [Program page assertions](../tests/Feature/Pages/ProgramPageTest.php)
- [Workout store flow](../tests/Feature/Workout/WorkoutStoreTest.php)
- [Workout authorization coverage](../tests/Feature/WorkoutAuthorizationTest.php)

## Unit Tests

Use a unit test when the behavior is primarily inside one class and can be verified without going through HTTP.

Good candidates in this codebase:

- Service classes
- Query builders
- API resources
- Enums and model concerns

Unit tests in this project still commonly use the database when the class depends on Eloquent behavior. Keep the scope narrow even when `RefreshDatabase` is needed.

Representative example:

- [Workout service behavior](../tests/Unit/Services/Workout/WorkoutServiceTest.php)

## Required Project Conventions

- Always use the `#[Test]` attribute for test methods.
- Use the `#[DataProvider]` attribute when a data provider is needed.
- Organize tests under `tests/Feature` or `tests/Unit` by responsibility.
- Name tests after observable behavior.
- Use factories instead of hand-built model arrays when possible.
- Catalog factories set the English `name` / `description` as attributes. Use `withTranslation($field, $value, $locale)` for another locale and `withoutTranslation($field)` to remove a field's text.
- Lazy loading throws in tests (`LazyLoadingViolationException`). Fix the query with an eager load; do not disable the guard.

## Authorization Coverage

Authorization coverage is mandatory for user-owned functionality.

For each new endpoint or page involving user-owned records:

- Add an owner-allowed test.
- Add an other-user denied test for mutations.
- Add an owner-scoped not-found test for reads when the query is intentionally scoped to the authenticated user.

Current project pattern:

- Mutations typically assert `403 Forbidden`.
- Owner-scoped reads typically assert `404 Not Found` for other users.

Use [WorkoutAuthorizationTest](../tests/Feature/WorkoutAuthorizationTest.php) as the reference pattern.

For role-gated endpoints (e.g. admin catalog management), also cover access by role: assert a non-admin user receives `403`, an admin user succeeds, and a guest receives `401`. Create admins with the `User::factory()->admin()` state. Use [EquipmentAdminTest](../tests/Feature/Admin/EquipmentAdminTest.php) as the reference pattern.

## Database Assertions

When a change writes or deletes records:

- Assert the response first.
- Assert the persisted state with `assertDatabaseHas` or `assertDatabaseMissing`.
- For cascades, assert both the parent deletion and the dependent records.

Use [WorkoutStoreTest](../tests/Feature/Workout/WorkoutStoreTest.php) and [WorkoutServiceTest](../tests/Unit/Services/Workout/WorkoutServiceTest.php) as reference patterns.

## Time-Sensitive Behavior

If behavior depends on dates or timestamps, freeze time explicitly with `Carbon::setTestNow(...)` so assertions stay deterministic.

## Validation Tests

When adding or changing validation:

- Test the happy path.
- Test the failing input.
- Assert the relevant validation errors with `assertFieldErrors` (path or pointer, plus the expected `FieldErrorCode` when the rule matters).
- Only assert exact custom messages (`errors[].detail`) when the project intentionally defines a domain-specific message.

## Service and Transaction Tests

Service tests should verify the full business outcome, not just that methods were called.

When a service performs multi-step writes:

- Assert the created, updated, or deleted records.
- Add rollback-oriented coverage when the workflow is complex or failure-prone.

## Test Writing Checklist

- Cover the changed behavior, not unrelated paths.
- Prefer one focused assertion flow per test.
- Reuse existing factories and project patterns.
- Keep setup proportional to the behavior under test.
- Run the smallest relevant test command before finishing.

## Coverage

Coverage is computed and enforced entirely inside GitHub Actions (`.github/workflows/tests.yml`). There is no external coverage service.

### How it is measured

- Backend: PHPUnit with the `pcov` driver, run as `php artisan test --coverage --min=N`. The command prints a per-file table and exits non-zero when line coverage drops below `N`. It also writes `coverage.xml` (Clover) and an HTML report in `coverage-html/`.
- Frontend: Vitest's V8 provider (`npm run test:coverage`). `coverage.thresholds.lines` in `apps/web/vitest.config.js` fails the run below the floor. Reports are written to `apps/web/coverage/` (`lcov.info`, `coverage-summary.json`, and the HTML report at `index.html`).

### Floors

| Stack | Where the floor lives | Current floor |
| --- | --- | --- |
| Backend | `BACKEND_MIN` env in the `php-tests` job of `tests.yml` | 95 |
| Frontend | `thresholds.lines` in `apps/web/vitest.config.js` | 77 |

Floors are kept a couple of points below the measured value so unrelated refactors do not break CI. Raise them when coverage improves; never lower them to make a PR pass without discussing why.

### Where to see results

- Each CI run writes both percentages to the job summary (Actions → run → Summary).
- Artifacts `php-coverage` and `web-coverage` hold the HTML reports for browsing uncovered lines.
- On every push to `main`, the `coverage-badges` job renders `backend.svg` and `frontend.svg` itself (no badge service) and commits them to the orphan `badges` branch. The README embeds them via `https://github.com/<owner>/<repo>/raw/badges/<name>.svg`, which GitHub serves to anyone with repository access, so this works for a private repository. That branch is machine-managed; do not edit it by hand.

### Local coverage

The dev `app` container has no coverage driver, so `--coverage` only works in CI for the backend. Frontend coverage runs locally with `docker compose exec -T node npm run test:coverage`.

## Related

- [Code Style](code-style.md)
- [Architecture](architecture.md)
- [Programs](features/programs.md)
- [Workout Logging](features/workout-logging.md)

## iOS Tests

The iOS app (`apps/ios`) is tested on the host with the Xcode toolchain, not in Docker.

- **Package tests** (`Packages/WorkoutsCore`, `Packages/WorkoutsAPI`): Swift Testing suites run with `swift test` inside the package directory. They need no simulator because the packages are Foundation-only; anything that needs UIKit stays in the app target.
- **App tests** (`Tests/MyWorkoutsTests`): Swift Testing against the app target, run with `xcodebuild test` on a simulator (see `apps/ios/README.md` for the command).
- **UI tests** (`UITests/MyWorkoutsUITests`): a small XCUITest smoke suite. The launch test is self-contained; the login flow needs the dev stack, runs only with `TEST_RUNNER_UITEST_LIVE_BACKEND=1`, and needs a signed simulator build (no `CODE_SIGNING_ALLOWED=NO`) because the session lives in the Keychain. Skipped in the per-PR CI job (`-skip-testing:MyWorkoutsUITests`) to keep macOS minutes low; run them locally before a release.
- **Lint**: `swift format lint --strict --recursive apps/ios` is enforced by CI.
- **Patterns**: view models are tested through fake repositories (actors in the test target) and `LoadState`; the generated client is exercised with `StubTransport`, which answers per operation id; fixtures under `Tests/WorkoutsCoreTests/Fixtures` were captured from the seeded dev API. UI tests use accessibility identifiers (`login.email`, `editor.save`, …), start signed out with `--reset-session`, and must scroll to offscreen rows in forms. `TEST_RUNNER_UITEST_CONTENT_SIZE=UICTContentSizeCategoryAccessibilityXXXL` runs them at the largest text size; the live flow passes there too.

`.github/workflows/ios.yml` runs lint, package tests and app tests on a macOS runner only when `apps/ios/**`, `contracts/openapi.yaml` or the workflow itself changed; otherwise the job is skipped, which still satisfies branch protection.

## Contract Tests

`contracts/openapi.yaml` is enforced against a running API with [Schemathesis](https://schemathesis.io): every documented operation is exercised with generated positive and negative data and every response is checked for a documented status code, content type, and schema. This is the acceptance gate for any backend that claims to implement the contract.

```bash
scripts/contract-test.sh http://localhost:8080            # dev stack (Docker image of Schemathesis)
SCHEMATHESIS=schemathesis scripts/contract-test.sh URL     # local binary
```

The run logs in as the seeded admin (`test@example.com`) and fuzzes writes, so use a disposable database and reseed afterwards (`php artisan migrate:fresh --seed`). Raise `API_RATE_LIMIT_PER_MINUTE` for the run. `.github/workflows/contract.yml` runs it on every push with a fresh MySQL and `php artisan serve`.

Excluded from fuzzing: logout (would revoke the token in use), profile delete/update (would remove or rename the account) and the unimplemented social login endpoint.

### Contract lint

Before Schemathesis runs, the contract itself is linted in `.github/workflows/lint.yml` (job `openapi-lint`) by two tools, both runnable locally without installing anything:

```bash
npx --yes @redocly/cli@latest lint contracts/openapi.yaml --config contracts/redocly.yaml
npx --yes @stoplight/spectral-cli@latest lint contracts/openapi.yaml --ruleset contracts/.spectral.yaml --fail-severity warn
```

Redocly checks structure and that every example is valid against its schema. Spectral applies the Zalando ruleset plus the project's `house-*` rules from `contracts/.spectral.yaml`; a warning fails the job. A rule may be disabled only with a comment on that line stating the reason or the story that re-enables it. What each rule means and which are off is in [API Design](api-design.md).


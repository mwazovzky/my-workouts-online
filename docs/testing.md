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
npm run test:frontend
npm run test:frontend:coverage
php artisan test --compact
```

## Suite Structure

- `tests/Feature/` covers the full HTTP stack, page rendering, authorization, validation, and database-side outcomes.
- `tests/Unit/` covers isolated logic such as services, resources, enums, models, and query builders.
- `resources/js/**/*.test.js` covers frontend utilities, composables, and component behavior with Vitest.
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

Frontend coverage is generated with Vitest's V8 coverage provider and written to `coverage/frontend/` in CI.

First-reference examples in this repo:

- [Translation composable](../resources/js/composables/useTranslation.test.js)
- [Date formatting utility](../resources/js/utils/date.test.js)
- [Program card component](../resources/js/Components/ProgramCard.test.js)

Keep frontend tests focused:

- Prefer one component or module per file.
- Assert the behavior that matters, not implementation details.
- Stub child components when the parent behavior is the thing being tested.
- Mock Inertia helpers when testing composables that depend on `usePage()` or `router`.

## Feature Tests

Use a feature test when the change affects any of the following:

- A page, route, controller, form request, policy, middleware, or redirect
- Validation behavior or error messages
- Inertia props or rendered page components
- Database changes triggered through an HTTP request

Common patterns in this project:

- Use `RefreshDatabase`.
- Create data with factories.
- Authenticate with `actingAs($user)`.
- Hit named routes with `route(...)` instead of hard-coded URLs.
- Assert both the HTTP response and the persisted database state.
- For Inertia pages, assert the component name and only the props that matter.

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

## Inertia Assertions

For Inertia responses:

- Assert the rendered component.
- Assert only the props relevant to the behavior under test.
- Prefer `missing(...)` assertions when verifying that sensitive or unnecessary data is not exposed.

Use [ProgramPageTest](../tests/Feature/Pages/ProgramPageTest.php) as the reference pattern.

For client-only behavior inside Vue components, prefer Vitest component tests over expanding PHP page assertions.

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
- Assert the relevant validation errors.
- Only assert exact custom messages when the project intentionally defines a domain-specific message.

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

Coverage is calculated in CI for both stacks before any external upload happens.

- Backend coverage is generated by PHPUnit as `coverage.xml`.
- Frontend coverage is generated by Vitest as `coverage/frontend/lcov.info`.
- GitHub Actions stores both reports as artifacts.
- Codecov is used as the reporting and badge layer, not as the coverage calculator itself.

The CI pipeline uploads two separate Codecov flags:

- `backend`
- `frontend`

This allows separate reporting, PR visibility, and private-repo flag badges once the repository's Codecov badge token is added to the README badge URLs.

## Related

- [Code Style](code-style.md)
- [Architecture](architecture.md)
- [Programs](features/programs.md)
- [Workout Logging](features/workout-logging.md)
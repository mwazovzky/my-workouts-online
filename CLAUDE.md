# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Workout tracking app: users browse programs, enroll, log workouts, and track sets. Monorepo:

- `services/api-php/` — Laravel 12 JSON API (PHP 8.4, MySQL). No HTML rendering, no sessions; JWT auth (`jwt` is the only guard).
- `apps/web/` — standalone Vue 3 SPA (Composition API, `<script setup>`, vue-router, Pinia, Tailwind v3) consuming the API.
- `apps/ios/` — native SwiftUI iOS app (Swift 6, iOS 17+, XcodeGen `project.yml`, local packages `WorkoutsCore` and `WorkoutsAPI`) consuming the same API; see `apps/ios/README.md`. Planned in `planning/epics/ios-app/`.
- `services/api-go/` — a second backend in Go implementing the same contract, built story by story in `planning/epics/go-backend/` (health probes, `getMeta`, every identity operation, the catalog's read side and enrollment, every workout operation, the dashboard, the admin catalog operations, catalog sync from `catalog/`, Resend mail and the support-inbox webhook with its River worker, and its Postgres schema; only social login answers 501; not routed by nginx). See `services/api-go/README.md`.
- `contracts/openapi.yaml` — the API contract every client and backend implements.
- `docker/` — `api` image (php-fpm), `web` image (nginx + SPA build, proxies `/api` to php-fpm), `api-go` image (Go toolchain dev stage, distroless production stage), dev nginx config.

Full product docs in `docs/` — see `docs/README.md` for index. Note: the Laravel Boost guideline sections below that describe Inertia do not apply; this backend renders no pages.

## Common Commands

The dev stack is Docker Compose (`docker compose up -d`): `app` (php-fpm on `services/api-php`), `node` (Vite dev server for `apps/web`), `nginx` (http://localhost:8080 serves the SPA and `/api`), `mysql`, `api-go` (the Go API on http://localhost:8081, toolchain container with the repo bind-mounted; metrics on :9090), `worker-go` (the Go job worker, `workouts work`; metrics on :9091), `postgres` (used only by the Go API). Run tools through the containers unless PHP/Node are installed on the host.

### Backend (`services/api-php`)
```bash
docker compose exec -T app php artisan test --compact                        # All backend tests
docker compose exec -T app php artisan test --compact tests/Feature/Workout/  # A directory
docker compose exec -T app php artisan test --compact --filter=testMethodName # One test
docker compose exec -T app vendor/bin/pint --dirty                           # PHP formatting
docker compose exec -T app php artisan catalog:sync --dry-run                # Preview catalog data file changes
docker compose exec -T app php artisan catalog:sync                          # Apply the shared YAML catalog/ to the database
```
Backend tests use SQLite in-memory (`phpunit.xml`, values forced so container env cannot leak in). PHPUnit only — no Pest.

### Web SPA (`apps/web`)
```bash
docker compose exec -T node npm test          # Vitest
docker compose exec -T node npm run lint:fix  # ESLint auto-fix
docker compose exec -T node npm run format    # Prettier
docker compose exec -T node npm run build     # Production build
docker compose exec -T node npm run api:types # Regenerate TS types from contracts/openapi.yaml
```

After modifying any `.js` or `.vue` file in `apps/web`, always run `npm run lint:fix && npm run format && npm test` there before finalizing.

### iOS app (`apps/ios`)
Runs on the host (Xcode + `brew install xcodegen`), not in Docker.
```bash
swift format --in-place --recursive apps/ios                               # Swift formatting
swift format lint --strict --recursive apps/ios                            # Swift lint (CI runs this)
(cd apps/ios/Packages/WorkoutsCore && swift test)                          # package tests, no simulator
(cd apps/ios && xcodegen generate)                                         # regenerate MyWorkouts.xcodeproj after editing project.yml
xcodebuild test -project apps/ios/MyWorkouts.xcodeproj -scheme MyWorkouts -destination 'platform=iOS Simulator,name=iPhone 18 Pro' -skip-testing:MyWorkoutsUITests
swift apps/ios/scripts/seed-string-catalog.swift                           # merge web UI strings into Localizable.xcstrings
```

### Go API (`services/api-go`)
Runs in the `api-go` container (no Go on the host needed); with Go 1.27 on the host, drop the `docker compose exec -T api-go` prefix.
```bash
docker compose exec -T api-go make check     # the gate CI runs: tidy, fmt, vet, golangci-lint, generated-code diff, race tests + coverage, govulncheck
docker compose exec -T api-go make fmt       # gofumpt + goimports
docker compose exec -T api-go make generate  # regenerate httpapi/gen from contracts/openapi.yaml and the sqlcgen packages from sql/ (commit the result)
docker compose exec -T api-go go run ./cmd/workouts migrate status  # migrations applied to the dev database (the container runs `migrate up` on start)
docker compose exec -T api-go go run ./cmd/workouts seed   # verified demo admin test@example.com / password, then the catalog (Go database)
docker compose exec -T api-go go run ./cmd/workouts catalog sync --dry-run  # preview applying catalog/ (YAML) to the Go database
CONTRACT_INCLUDE=$(grep -v '^#' services/api-go/contract-scope.txt | paste -sd, -) scripts/contract-test.sh http://localhost:8081  # Schemathesis on the operations Go implements
docker compose exec -T api-go go test -race ./internal/platform/health/  # one package
docker compose restart api-go worker-go      # rebuild and restart after code changes
```

After modifying any `.go` file, `.sql` file or `contracts/openapi.yaml`, always run `make check` in `services/api-go` before finalizing. Database tests run against the compose `postgres` inside the container (`TEST_DATABASE_URL`) and against testcontainers in CI; new files must be `git add`ed before `make check` or the generated-code check flags them. Conventions are in `services/api-go/docs/code-style.md`; decisions are ADRs in `services/api-go/docs/adr/`.

After modifying any `.swift` file, always run `swift format --in-place` on it and the affected package or app tests before finalizing. Views live in the app target; anything testable without UIKit goes in `Packages/`.

After modifying any `.php` file, always run `vendor/bin/pint --dirty` and `php artisan test --compact` in `services/api-php` before finalizing.

After modifying `contracts/openapi.yaml` or any API response shape, lint the spec and run `scripts/contract-test.sh` against the dev stack (then reseed). Lint: `docker run --rm -v "$PWD/contracts:/spec:ro" -w /spec redocly/cli:latest lint openapi.yaml --config redocly.yaml` and `npx --yes @stoplight/spectral-cli@latest lint contracts/openapi.yaml --ruleset contracts/.spectral.yaml --fail-severity warn`. A Spectral rule may be disabled only with a comment giving the reason or the story that re-enables it.

## Architecture

### Backend Patterns

- **Service + Interface DI** — Business logic in `app/Services/{Domain}/` (e.g., `Workout/`), each behind an interface, bound in `AppServiceProvider`.
- **Custom Query Builders** — `app/QueryBuilders/` extends Eloquent Builder with composable scopes (e.g., `WorkoutBuilder` with `ownedBy()`, `withTemplate()`, `latestUpdated()`).
- **Polymorphic Activities** — `Activity` morphs to both `WorkoutTemplate` and `Workout`. Morph map in `AppServiceProvider`: `workout_template`, `workout`.
- **Two-Axis Measurement** — Sets use `effort_value` (reps/seconds via `Exercise.effort_type`) + `difficulty_value` via `Equipment.difficulty_unit` (kilograms/pounds/plates = weight, `heart_rate_zone` = zone 1–5 validated by `HeartRateZoneWithinRange`, `none` = no difficulty field). All combinations are valid.
- **Eloquent Resources** — All API responses go through API Resources (`app/Http/Resources/`). Per-locale `translations` are returned only by the `Admin*` resources (and `EquipmentResource`, which is admin-only); public resources return the localized value.
- **Form Requests** — Validation in `app/Http/Requests/`. Authorization via policies in `app/Policies/`. Admin `translations` rules come from the `HasTranslationRules` trait, built from `config('app.available_locales')`.
- **Lazy loading** — `Model::preventLazyLoading()` is on outside production; eager load relations instead of reading them per row.
- **Role-Based Authorization** — `User::hasRole()`/`isAdmin()` back the `admin` middleware alias (`EnsureUserIsAdmin`, registered in `bootstrap/app.php`) plus per-model policies. `UserResource.is_admin` drives admin-only UI. Admin catalog CRUD (equipment, categories, exercises, workout templates, programs) lives in `app/Http/Controllers/Api/Admin/` with logic in `app/Services/Admin/`; `WorkoutTemplateService` syncs nested activities/sets and `ProgramService` syncs weekday template assignments, both transactionally. `Exercise` uses `SoftDeletes` (delete = retire); `Activity::exercise()` is `->withTrashed()` so history resolves retired exercises; templates/programs are hard-deleted with reference guards.
- **Catalog Sync** — The shipped catalog is the YAML in the repository's `catalog/`, read by both backends (`config('catalog.path')`, `CATALOG_PATH` in the containers; records keyed by `slug`; `exercises/` and `workout-templates/` are directories). `catalog:sync` (`app/Services/Catalog/`, run by every deploy and by `CatalogSeeder`) validates, then applies them transactionally: rows match by `slug` or are adopted by English name / `previous_names`; the data files win for managed records; admin-created records (null `slug`) are untouched; removals go in `retired.yaml`. Each validator rule has a `CatalogRule` id shared with Go and a failing fixture in `catalog/testdata/invalid/<rule>/`. Change shipped content by editing the data files, not seeders. `UserSeeder`/`WorkoutSeeder` are demo-only and throw in production.
- **Internationalization** — `NegotiateApiLocale` middleware sets the locale from `Accept-Language`. Translatable models store their source text (`name`, `description`) in their own columns, in `config('app.source_locale')`; the polymorphic `translations` table holds other locales only. The `HasTranslations` trait reads the requested locale and falls back to the column, and writes through `createWithTranslations()` (seed/create) and `updateTranslations()` (edit; blank values removed for fallback). Backend strings embedded in responses live in `lang/*.json`; UI strings live in `apps/web/src/i18n/locales`, accessed via the `useTranslation` composable.

### Frontend Patterns

- **Pages** in `apps/web/src/pages/`, **components** in `apps/web/src/components/`, **layouts** in `apps/web/src/layouts/`.
- **UI primitives** (badge, button, card, skeleton, etc.) in `apps/web/src/components/ui/` — check these before creating new ones.
- **Stores** (Pinia) in `apps/web/src/stores/` — `auth` (in-memory access token, user, login/logout/bootstrap), `locale` (client-owned UI catalogs), `meta`.
- **Composables** in `apps/web/src/composables/` — `useApi`, `useApiForm` (422 errors flattened to `form.errors`), `useEnrollment`, `useTranslation` (`t()`), `usePageTitle` (every title ends in "– My Workouts Online"), `usePageMeta` (description, canonical, Open Graph for public pages), `useActiveRoute`, `useLogout`.
- **Routing** in `apps/web/src/router/` — named routes mirror the old Laravel route names; guards read route meta `guest` / `auth` / `verified` / `admin`.
- **Prerendered public pages** — `npm run build` also prerenders `PUBLIC_PAGES` (`src/lib/crawl.js`: `/`, `/privacy`, `/support`) and the program catalog (`/catalog/programs/{slug}`, built from `catalog/` YAML through the `virtual:public-catalog` module, `src/lib/catalogFiles.js`), each with a `/ru` version, through `src/entry-server.js` and `prerender.js`; code those pages run must not touch `window`/`document`/`localStorage` at import or in setup. See `docs/architecture.md`.
- **API client** `apps/web/src/lib/api.js` — bearer + `Accept-Language` headers, single-flight refresh-and-retry on 401 `token_expired`.

### iOS Patterns

- **Layers** — views in `apps/ios/App/`, everything else in `apps/ios/Packages/`: `WorkoutsAPI` (generated client from the symlinked contract, middlewares, `APIError`), `WorkoutsCore` (domain models + mappers, `AuthSession`/`AppSession`, repository protocols with `API*Repository` implementations, `@Observable` view models, `WorkoutEditorModel` + `DraftStore`, `DeepLinkCoordinator`, `LocaleSync`). Views get `AppDependencies` via the `dependencies` environment key and `AppSession`, `DeepLinkCoordinator`, `Toaster` as environment objects.
- **Adding a screen** — repository protocol + API implementation in Core (map every generated output case), an `@Observable` view model with `LoadState`, a view using `LoadingStateView` / `EmptyStateView` / `ErrorStateView`, a fake repository and Swift Testing suite, an `AppRoute` case if pushable, and strings in `Localizable.xcstrings` (en + ru).
- **Gotchas** — set `.environment(\.navigationPath, $path)` on the `NavigationStack`, not its root view; a modifier reading an `@Environment` object must sit inside the `.environment(...)` that provides it; overlays need `.allowsHitTesting(false)`; `.accessibilityIdentifier` on a container propagates to its texts (query `staticTexts` in UI tests); confirmation-dialog buttons need `.firstMatch`; UI tests launch with `--reset-session`; never pass `CODE_SIGNING_ALLOWED=NO` for UI runs (Keychain).

### Data Model

```
Program <-M:N-> WorkoutTemplate (pivot: weekday)
Program <-M:N-> User           (pivot: program_user — enrollment, unique per user and program)
WorkoutTemplate <-morph- Activity
Workout      <-morph- Activity
Workout -> User, WorkoutTemplate
Activity -> Exercise
Activity <-1:N- Set
Exercise -> Equipment
Exercise <-M:N-> Category
{Exercise,Equipment,Category,Program,WorkoutTemplate} <-morph- Translation (non-source locales)
```

[DB diagram](https://dbdiagram.io/d/workouts-68a1ae421d75ee360ae77ad8)

### CI

GitHub Actions: `tests.yml` (backend + web), `code-style.yml` (Pint), `lint.yml` (ESLint/Prettier for `apps/web`, OpenAPI lint), `contract.yml` (Schemathesis against a live API), `ios.yml` (swift-format, package tests, `xcodebuild test`; the macOS job is skipped unless `apps/ios/**` or the contract changed, and a skipped job still passes branch protection), `go.yml` (`make check` in `services/api-go`; skipped unless `services/api-go/**`, `docker/api-go/**`, `catalog/**` or the contract changed), `contract-go.yml` (Schemathesis against the Go API for the operations in `services/api-go/contract-scope.txt`; same kind of path filter), `deploy.yml`.

### Deployment

Docker Compose: `web` (nginx + SPA) and `api` (php-fpm) images built by the deploy workflow, plus `worker`, `mysql`, `alloy`. Every deploy runs `migrate` and then `catalog:sync`; production is never seeded with `db:seed`. The first admin is granted with `php artisan user:grant-admin {email}`. See `docs/deployment.md`. iOS releases are tagged `ios-v*` (never `v*`), run the `ios` workflow with UI tests, and are archived and uploaded from Xcode; see the iOS section of `docs/deployment.md`.

## Documentation

Project coding standards: `docs/code-style.md` (PHP and web); Go: `services/api-go/docs/code-style.md`.

API design standard (naming, envelope, errors, status codes): `docs/api-design.md`.

Documentation standard (templates, rules, quality checklist): `docs/documentation-standard.md`.

Always update documentation in `docs/` after changing app logic (new features, modified routes, changed data model, etc.).

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.17
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/vue3 (INERTIA) - v2
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

## Inertia

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (`vite.config.js`).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use the `search-docs` tool for accurate guidance on all things Inertia.

<code-snippet name="Inertia Render Example" lang="php">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>

=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 and v2. Check the documentation before making any changes to ensure we are taking the correct approach.

### Inertia v2 New Features
- Deferred props.
- Infinite scrolling using merging props and `WhenVisible`.
- Lazy loading data on scroll.
- Polling.
- Prefetching.

### Deferred Props & Empty States
- When using deferred props on the frontend, you should add a nice empty state with pulsing/animated skeleton.

### Inertia Form General Guidance
- The recommended way to build forms when using Inertia is with the `<Form>` component - a useful example is below. Use the `search-docs` tool with a query of `form component` for guidance.
- Forms can also be built using the `useForm` helper for more programmatic control, or to follow existing conventions. Use the `search-docs` tool with a query of `useForm helper` for guidance.
- `resetOnError`, `resetOnSuccess`, and `setDefaultsOnSuccess` are available on the `<Form>` component. Use the `search-docs` tool with a query of `form component resetting` for guidance.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== phpunit/core rules ===

## PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-vue/core rules ===

## Inertia + Vue

- Vue components must have a single root element.
- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="vue">

    import { Link } from '@inertiajs/vue3'
    <Link href="/">Home</Link>

</code-snippet>

=== inertia-vue/v2/forms rules ===

## Inertia v2 + Vue Forms

<code-snippet name="`<Form>` Component Example" lang="vue">

<Form
    action="/users"
    method="post"
    #default="{
        errors,
        hasErrors,
        processing,
        progress,
        wasSuccessful,
        recentlySuccessful,
        setError,
        clearErrors,
        resetAndClearErrors,
        defaults,
        isDirty,
        reset,
        submit,
  }"
>
    <input type="text" name="name" />

    <div v-if="errors.name">
        {{ errors.name }}
    </div>

    <button type="submit" :disabled="processing">
        {{ processing ? 'Creating...' : 'Create User' }}
    </button>

    <div v-if="wasSuccessful">User created successfully!</div>
</Form>

</code-snippet>

=== tailwindcss/core rules ===

## Tailwind CSS

- Use Tailwind CSS classes to style HTML; check and use existing Tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc.).
- Think through class placement, order, priority, and defaults. Remove redundant classes, add classes to parent or child carefully to limit repetition, and group elements logically.
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing; don't use margins.

<code-snippet name="Valid Flex Gap Spacing Example" lang="html">
    <div class="flex gap-8">
        <div>Superior</div>
        <div>Michigan</div>
        <div>Erie</div>
    </div>
</code-snippet>

### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v3 rules ===

## Tailwind CSS 3

- Always use Tailwind CSS v3; verify you're using only classes supported by this version.
</laravel-boost-guidelines>

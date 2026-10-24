# Architecture

## Stack

Monorepo — `services/api-php`: Laravel 12 JSON API · `apps/web`: Vue 3 SPA (Composition API, `<script setup>`, vue-router, Pinia) · Tailwind v3 · MySQL · Vite · Docker Compose (nginx `web` image + php-fpm `api` image). The API renders no HTML; the public pages are prerendered to HTML when the web app is built, and every other page is rendered by the SPA in the browser.

## Key Patterns

**Service + Interface DI** — Business logic in service classes (`WorkoutService`), each behind an interface, bound in `AppServiceProvider`.

**Query Builder** — `WorkoutBuilder` extends Eloquent Builder with composable scopes: `ownedBy()`, `withTemplate()`, `withActivitiesCount()`, `latestUpdated()`.

**Polymorphic Activities** — `Activity` morphs to `WorkoutTemplate` or `Workout`. Morph map in `AppServiceProvider`: `workout_template`, `workout`, `exercise`, `category`, `equipment`, `program`.

**API Contract** — `contracts/openapi.yaml` (OpenAPI 3.1) is the source of truth for every client and backend. It is linted in CI by Redocly and by Spectral with the house ruleset (`contracts/.spectral.yaml`: the Zalando guidelines plus `house-*` rules), and enforced by Schemathesis against a live API (`scripts/contract-test.sh`, `.github/workflows/contract.yml`). TypeScript types for the SPA are generated from it. The rules a contract change must follow are in [API Design](api-design.md).

**API Errors (Problem Details)** — Every non-2xx response under `api/*` is an RFC 9457 problem (`application/problem+json`: `type`, `title`, `status`, `detail`, `instance`, `code`), built by `App\Exceptions\Api\ApiErrorResponse` and wired in `bootstrap/app.php`: `fromHttpException()` for HTTP exceptions (an explicit code is thrown as `ApiException(status, ErrorCode, detail)`, e.g. `in_use` on reference guards), `fromValidation()` for 422s (per-field `errors` with an RFC 6901 pointer and a `FieldErrorCode`), `fromThrowable()` for everything else (500 `internal_error`). The codes and their titles live in `App\Enums\ErrorCode` and `App\Enums\FieldErrorCode`, mirroring the contract enums. `LogContext` gives every request an id, logs it, returns it as `X-Request-Id` and puts it in `instance`, so a user-reported error matches its log lines. The shape, the enums and how to add a code are in [API Design](api-design.md#errors).

**iOS session** — `apps/ios/Packages/WorkoutsCore`: `AuthSession` (actor) owns the token pair in a `TokenStore` (Keychain in the app, memory in tests) and performs single-flight refreshes; `AuthMiddleware` (in `WorkoutsAPI`) adds the bearer and retries once after a `token_expired` refresh; `AppSession` (`@Observable`, main actor) exposes `bootstrapping / signedOut / unverified / ready` for the root view. Repositories (`AuthRepository`, `MeRepository`) wrap the generated client and map every output case to a domain value or an `APIError`. `AppDependencies.make` wires two clients: one without a session for login/register/refresh, one with `AuthMiddleware` for everything else. Every client built by `APIClientFactory` runs `EntityTagMiddleware` first: swift-openapi-runtime percent-encodes header values, which would turn `If-Match: "42-7"` into `%2242-7%22`, so the middleware restores the literal tag.

**iOS client generation** — `apps/ios/Packages/WorkoutsAPI` generates a Swift client from `contracts/openapi.yaml` at build time (`swift-openapi-generator` build plugin; the contract is symlinked into the package and nothing generated is committed). `WorkoutsCore` maps the generated types to domain models and references every field the app uses, so removing or renaming a field in the contract breaks the iOS build in CI. Two contract conventions exist for this generator: nullable enums are inlined (`type: [string, 'null']` with the values) and optional object fields are omitted rather than `null`.

**Eloquent Resources** — All API responses go through API Resources (`ProgramResource`, `WorkoutResource`, `ActivityResource`, `SetResource`, `WorkoutTemplateResource`, `UserResource`). Optional object fields (`workout_template`, `in_progress_workout`) are omitted when there is nothing to return, never set to `null`, so generated clients keep them as optional typed fields.

**Form Requests** — Validation via dedicated request classes, all extending `App\Http\Requests\ApiFormRequest`, which rejects any key the rules do not declare (422, field code `unknown_field`, nested pointers included) so request bodies are as strict as the contract's `additionalProperties: false`. Authorization via policies separately.

**Role-Based Authorization** — `User::hasRole()` / `User::isAdmin()` check the `roles` relation. The `admin` middleware alias (`EnsureUserIsAdmin`, registered in `bootstrap/app.php`) gates admin web and API route groups; per-model policies (`EquipmentPolicy`, `CategoryPolicy`, `ExercisePolicy`, `WorkoutTemplatePolicy`, `ProgramPolicy`) provide defense-in-depth. `UserResource` exposes `is_admin`, so the shared `auth.user` prop drives admin-only navigation. Admin write endpoints live under `App\Http\Controllers\Api\Admin` with business logic in `App\Services\Admin` (`WorkoutTemplateService` syncs a template's nested activities and sets; `ProgramService` syncs a program's weekday template assignments — both transactionally).

**Prerendered public pages** — The web build (`npm run build`) runs `vite build`, then `vite build --ssr src/entry-server.js` and `prerender.js`, which renders every public page (`PRERENDER_PATHS`: `PUBLIC_PAGES` in `src/lib/crawl.js` — `/`, `/privacy`, `/support` — and the program and exercise catalogs, each with its `/ru` version) as a signed-out visitor and writes `dist/<path>/index.html`: the page's markup inside `<div id="app" data-prerendered="<path>">`, and its language, title and head tags from `usePageMeta`. The untouched shell is kept as `dist/spa.html`; nginx serves a prerendered file when one exists and `spa.html` otherwise. The build fails if a page redirects, has no `<h1>` or head, two pages in one language share a title or description, or the pages and the sitemap differ. Pages are rendered one at a time: the guards read the active Pinia. In the browser, `main.js` first restores the session and resolves the route; it hydrates the markup only when the visitor is a guest still on the prerendered page, and otherwise renders from scratch and resets the head (`resetHead`). Code that runs while prerendering must not touch `window`, `document` or `localStorage` at import or in setup (`import.meta.env.SSR` is true there, and `auth.bootstrap()` resolves signed out without calling the API).

**Client Bootstrap** — Clients restore their session from the refresh cookie (`POST /api/v1/auth/refresh`), then load `GET /api/v1/me` (`UserResource`, includes `is_admin` and `email_verified_at`) and the public `GET /api/v1/meta` (locales, theme preferences).

**API Authentication (JWT)** — The API is its own token issuer (`App\Services\Auth\TokenService` behind `TokenServiceInterface`). `POST /api/v1/auth/login` returns a short-lived EdDSA-signed access token (`config('jwt.access_ttl')`, default 15 min) and starts a refresh-token *family* (`refresh_tokens` table, hashed at rest). `POST /api/v1/auth/refresh` rotates the refresh token; presenting an already-rotated token revokes the whole family (reuse detection). Web clients (`client: web`) receive the refresh token only as an httpOnly `SameSite=Strict` cookie scoped to the refresh path and must send `X-Requested-With`; native clients (`client: native`) receive it in the body. Access tokens carry `iss`, `aud`, `sub`, `jti`, `exp`, `auth_time` and `fam` (family id) and are verified by the `jwt` guard (`App\Auth\JwtGuard` + `JwtUserResolver`, the default and only guard) against the public key published at `/.well-known/jwks.json`. Keys come from `JWT_PRIVATE_KEY` / `JWT_PUBLIC_KEY` (`php artisan jwt:keys`). There are no sessions; a 401 on `api/*` carries a `code` (`unauthenticated` or `token_expired`). Password changes and account deletion revoke the user's refresh families. `POST /api/v1/auth/reauth` re-checks the password and reissues an access token with a fresh `auth_time` (step-up). Verification and reset emails link to `config('app.frontend_url')`; `App\Auth\EmailVerificationLink` signs and validates verification links against the named `verification.verify` route, which exists only for URL generation (it forwards to the SPA).

**Internationalization** — `NegotiateApiLocale` sets the locale from `Accept-Language`. Translatable models keep their source text in their own columns and other locales in the polymorphic `translations` table, read through the `HasTranslations` trait. `lang/*.json` holds the backend's own translated strings (labels and messages embedded in responses); UI strings live in each client. Details: [Internationalization](#internationalization).

**Lazy loading is rejected outside production** — `Model::preventLazyLoading()` is enabled in `AppServiceProvider`, so a query that drops an eager load fails loudly instead of running one query per row.

**Soft-deleted exercises** — `Exercise` uses `SoftDeletes`: an admin "delete" **retires** it (removed from catalog/pickers via the global scope) without touching workout history. `Activity::exercise()` is `->withTrashed()`, so historical workouts and templates still resolve a retired exercise's name/effort/difficulty. Reference guards that must see retired rows use `withTrashed()` (e.g. the equipment-in-use delete guard). Templates and programs are hard-deleted (a `Workout` copies its own `name` and owns its activities/sets, so no history is lost); a template can't be deleted while a program uses it (409).

**Catalog Sync** — The shipped catalog (equipment, categories, exercises, workout templates, programs) is defined in the YAML files of the repository's [`catalog/`](../catalog/README.md), each record keyed by a `slug`. Both backends read it: the Go API applies it with `workouts catalog sync`, the PHP API with `php artisan catalog:sync`, with the same rules and algorithm. `CatalogLoader` reads the files (an unknown key is an error) and maps enum values to their PHP enums. `php artisan catalog:sync` (`App\Services\Catalog\CatalogSyncService`, run by every production deploy and by `CatalogSeeder`) validates all files first (`CatalogValidator`; any violation aborts before a write; each rule has a `CatalogRule` id shared with the Go validator and a fixture in `catalog/testdata/invalid/`), then applies them in one transaction through the admin services. Rows are matched by `slug`; a row without a slug is adopted by its English name or one of the record's `previous_names`, so ids and workout history survive. The data files win: a managed record (non-null `slug`) is reset to the files on every run and unchanged records are skipped. Records created in the admin UI have no slug and are never touched. Nothing is deleted by omission: removals are listed in `catalog/retired.yaml`, exercises are soft-deleted, and other types are deleted only when nothing references them (otherwise a warning). `--dry-run` reports without writing; `--strict` writes nothing when there are warnings. `slug` is not fillable and not exposed by any resource. Besides structure (unique slugs, both locales, resolvable references, zones 1–5), the validator enforces content rules: every equipment item and category is used by an exercise, a repetition-based exercise has a rest time, and a template set on weight-based equipment carries no weight.

**Seeders** — `DatabaseSeeder` runs `RoleSeeder` and `CatalogSeeder` everywhere, and `UserSeeder` + `WorkoutSeeder` (demo admin and demo workouts) outside production only; both demo seeders throw in production. All seeders are re-runnable. The `Admin` and `User` roles are also inserted by a migration, so an environment that never seeds still has them.

**Two-Axis Measurement** — Sets use `effort_value` (reps/seconds) + `difficulty_value` (nullable). `Equipment.difficulty_unit` (kilograms, pounds, plates, heart_rate_zone, none) controls the load axis; `Exercise.effort_type` (repetitions, duration) controls work. "Bodyweight" equipment (`difficulty_unit = none`) hides the difficulty field. For `heart_rate_zone`, `difficulty_value` is an integer 1–5 (a heart-rate zone) — validated on save by `App\Rules\HeartRateZoneWithinRange` and entered via a 1–5 picker in the set editor; a blank zone stores null. All unit/effort combinations are valid.

## Key Decisions

**Programs and workouts are separate concepts** — Programs and templates are shared catalog data. User workouts are personal copies that can diverge from the original template.

**Bulk workout save** — Workout editing happens client-side and is persisted as a full activities-and-sets payload with `PUT /api/v1/workouts/{workoutId}/activities`. The service locks the workout row, checks the `If-Match` precondition against its `version` (`App\Http\WorkoutETag`, `"<id>-<version>"`), diffs, validates ownership, bumps the version and applies changes in one transaction. Every single-workout response carries the `ETag`; a stale editor gets 412 instead of overwriting a newer save.

**Repeat breaks template linkage** — Repeated workouts set `workout_template_id` to `null` so the repeated workout is treated as a user-owned copy, not a live projection of the original template.

**Prerendering with Vue's own server renderer** — Public pages are rendered at build time by a small script around `vue/server-renderer` (part of `vue`) and Vite's SSR build, not by `vite-ssg` or a Node server: no new dependency, no process in production, the router and `usePageMeta` stay as they are, and the signed-in app is unchanged. A page that needs to be found by search engines is added to `PUBLIC_PAGES` (or, like the program catalog, to `publicPaths`) and must render without the API.

**Public catalog from the YAML files** — The program and exercise catalog pages read the shipped catalog (`catalog/`) at build time, not the API: `src/lib/catalogFiles.js` parses it with `js-yaml` (a dev dependency, never in the browser bundle), `src/lib/publicCatalog.js` keeps what the pages show and fails the build on a broken reference, and the result reaches the pages as the `virtual:public-catalog` module, imported only by lazy-loaded pages so it stays out of the main bundle. The data is the same both backends sync from, needs no public endpoint and is fixed per build, which is what prerendering needs.

**Content is translated by the API, UI strings by the client** — Catalog content is stored once in its source language with per-locale overrides, and the API returns the value for the negotiated locale. UI strings belong to each client (`apps/web/src/i18n/locales`).

## iOS Client

`apps/ios` is a native SwiftUI app (Swift 6, iOS 17+) generated from `project.yml` by XcodeGen; the `.xcodeproj` is never committed. One app target holds the views; two local Swift packages hold everything testable without UIKit: `WorkoutsAPI` (the client generated from the contract, middlewares, `APIError`, the date transcoder) and `WorkoutsCore` (domain models and mappers, `AuthSession`, `AppSession`, repositories, `@Observable` view models, the editor model and draft store, deep links, locale sync).

- **Layers** — View → `@Observable` view model → repository protocol → generated `Client`. Repositories map every generated output case to a domain value or an `APIError`; views never see generated types. Fakes implement the repository protocols in tests.
- **Navigation** — a `TabView` with one `NavigationStack` per tab; `AppRoute` enumerates every pushable destination and `RouteDestinationView` resolves it. Pushed screens reach their tab's path through the `navigationPath` environment value, which must be set on the `NavigationStack` itself so destinations inherit it.
- **Screen state** — `LoadState<Value>` (`idle`, `loading`, `loaded`, `failed`) drives shared loading, empty and error views; a reload keeps content visible; forms use `FormErrors` (first message per field, one general banner).
- **Editor** — `WorkoutDraft` is the editable copy with device-side identities; `WorkoutEditorModel` holds the web editor's rules; the `DraftStore` persists unsaved edits and restores them while the server `updated_at` is unchanged.
- **Testing** — Swift Testing in the packages against `StubTransport` and fakes; XCUITest smoke flows in the app, including a live flow against the dev stack gated by `TEST_RUNNER_UITEST_LIVE_BACKEND=1` and started signed out via the `--reset-session` launch argument. Details in `apps/ios/README.md` and `docs/testing.md`.

## Internationalization

### Locale Resolution

- Supported locales: `en`, `ru`
- User preference is stored in `users.locale`
- API requests: `NegotiateApiLocale` uses only `Accept-Language` (negotiated against `available_locales`, fallback `en`). The stored profile locale is a synced preference the client sends back as the header. Responses carry `Content-Language` and `Vary: Accept-Language`
- `GET /api/v1/meta` lists `available_locales`; the SPA sends its active locale as `Accept-Language`
- Public pages exist once per language (`/privacy`, `/ru/privacy`). For a guest the URL sets the language without recording it as a choice; a guest who chose a language (switcher or a past sign-in) and a signed-in user are sent to the version in their language (`resolvePublicLocale` in `router/guards.js`)

### Translation Sources

- Source text lives in the `name` / `description` columns of each translatable table, written in `config('app.source_locale')` (English for the shipped catalog); `name` is required
- Other locales live in the polymorphic `translations` table, one row per record, locale and field
- Reads return the requested locale's row and fall back to the source text, so a record with no translation rows reads the same in every locale
- `HasTranslations::createWithTranslations()` creates records; `updateTranslations()` applies edits and removes blank values so reads fall back. Both route the source locale to the columns
- Assigning a translatable attribute (`$exercise->name = ...`) writes the source text
- Translations are auto-eager-loaded, and purged only on a real/force delete, so a soft-deleted record still resolves its name
- Per-locale values (`translations`) are returned only by the admin resources (`AdminExerciseResource`, `AdminCategoryResource`, `AdminProgramResource`, `AdminWorkoutTemplateResource`, `EquipmentResource`); public resources carry the localized value alone
- Admin validation rules for `translations` are built from `available_locales` by `HasTranslationRules`; only the source locale's name is required
- Shipped catalog content carries both locales in the `catalog/` data files (see Catalog Sync)
- Backend strings embedded in responses (weekday and status labels, guard messages) live in `lang/en.json` and `lang/ru.json`; UI strings live in `apps/web/src/i18n/locales`
- Validation and auth messages use Laravel language files under `lang/{locale}`

### Model Translation Rules

- `Exercise`, `Category`, `Equipment`, `Program`, and `WorkoutTemplate` are translated through `HasTranslations`
- User content is not re-translated after creation
- Workouts copy the translated template name at creation time

## Go API

`services/api-go` is a second backend being built against the same contract (the [Go backend epic](../planning/epics/go-backend/README.md)). The PHP API stays the production backend and nginx does not route to Go yet. In the dev stack it runs as `api-go` on http://localhost:8081 with its own `postgres` (17) service, which nothing else uses; today it answers the health probes, `GET /api/v1/meta`, the operations of its `identity` module (tokens, JWKS, email verification, password reset, `/me`) the read side of its `catalog` module (programs, enrollment, workout templates, the exercise picker) its `training` module (every workout operation, with the same `ETag`/`If-Match` scheme), the dashboard, the admin catalog operations (the catalog module's write side, with PHP's delete guards, [ADR 0012](../services/api-go/docs/adr/0012-admin-delete-guards.md)) and the Resend support-inbox webhook (its `inbox` module, [ADR 0013](../services/api-go/docs/adr/0013-support-inbox-and-jobs.md)), answers only social login with a 501 Problem, and migrates its schema on start; `workouts seed` and `workouts catalog sync` load the shared `catalog/` YAML. Modules do not reach into each other: `training` reads the catalog only through its `catalogapi` package of plain, localized DTOs (template snapshots and headers, exercise lookups), and `dashboard`, a read model that owns no tables and never writes, composes both through `catalogapi` and `trainingapi` ([ADR 0011](../services/api-go/docs/adr/0011-dashboard-read-model.md)). Who may call what comes from the contract itself: `security: []` operations are public, the rest need a bearer token, and most a verified email, and `Admin`-tagged ones an admin ([ADR 0007](../services/api-go/docs/adr/0007-identity-tokens-and-auth-policy.md)). Email goes through a mailer port (logged in development, sent through Resend with `MAIL_MAILER=resend`). Background jobs are River jobs in the same Postgres: a use case inserts its job in its own transaction, and `workouts work` (the `worker-go` service) works them. Both processes are instrumented with OpenTelemetry: spans per request (named by route) and per query, Prometheus metrics on a private admin port (request latency and count by route, database time, active and failed jobs), and log lines that carry the request's id, user and trace ids ([ADR 0014](../services/api-go/docs/adr/0014-observability.md)); the PHP API has logs only. Verification links are signed with the Go service's own key ([ADR 0008](../services/api-go/docs/adr/0008-go-native-signed-links.md)). Its error bodies, locale negotiation and request validation follow [API Design](api-design.md) like the PHP API's; the few deliberate differences in wording are listed in [ADR 0006](../services/api-go/docs/adr/0006-http-errors-locale-and-request-validation.md). Its HTTP layer is generated from `contracts/openapi.yaml` and committed, so a contract change that removes or renames a field breaks the Go build, as it breaks the iOS build. CI runs `make check` for it in the path-filtered `go` workflow.

Its data model matches the one below with two deliberate departures: translations are a `jsonb` column on each catalog row instead of the polymorphic `translations` table ([ADR 0004](../services/api-go/docs/adr/0004-translations-as-jsonb.md)), and templates and workouts have their own activity and set tables with real foreign keys instead of the polymorphic `activities` ([ADR 0005](../services/api-go/docs/adr/0005-split-activity-tables.md)). Enrollments and role assignments are unique by primary key.

The service's internal architecture (module layout, dependency rules, lifecycle) is documented with the service: [README](../services/api-go/README.md), [code style](../services/api-go/docs/code-style.md) and [decision records](../services/api-go/docs/adr/).

## Directory Guide

Only non-obvious locations listed.

| Path                          | Contains                                                                     |
| ----------------------------- | ---------------------------------------------------------------------------- |
| `apps/web/`                   | The Vue SPA (vue-router, Pinia) consuming `/api/v1`; served by the `web` nginx image |
| `apps/ios/`                   | The SwiftUI app: `project.yml`, `App/` (views, `Localizable.xcstrings`, assets), `Config/*.xcconfig`, `Packages/WorkoutsAPI`, `Packages/WorkoutsCore`, `UITests/`, `scripts/` |
| `services/api-php/`           | The Laravel API; `docker/nginx/api-locations.conf` is the only path from the SPA to it |
| `catalog/`                    | Shared YAML catalog both backends apply, test fixtures in `testdata/` |
| `services/api-go/`            | The Go API in progress: `cmd/workouts`, `internal/{kernel,platform,identity,catalog,training,dashboard,inbox,bootstrap}`, `migrations/` (goose), `sql/` (sqlc queries), `docs/adr/`, `Makefile` (`make check`) |
| `docker/api-go/`              | The Go API image: `dev` toolchain stage (compose) and distroless `api` stage |
| `docker/nginx/`               | nginx snippets shared by dev and production: API routes, SPA fallback, `/.well-known/apple-app-site-association` for iOS Universal Links (rendered from `APPLE_APP_ID`), security headers |
| `contracts/`                  | `openapi.yaml`, the API contract every client and backend implements       |
| `app/Services/{Domain}/`      | Service class + interface per domain (`Workout/`, `Admin/`, `Auth/`, `Catalog/`, `InboundEmail/`) |
| `app/Http/Controllers/Api/Admin/` | Admin catalog CRUD controllers (equipment, categories, exercises)       |
| `app/QueryBuilders/`          | Custom Eloquent builders                                                     |
| `app/Enums/`                  | `WorkoutStatus` (InProgress, Completed), `EffortType` (Repetitions, Duration), `DifficultyUnit` (Kilograms, Pounds, Plates, HeartRateZone, None), `Weekday` (Monday…Sunday, backed by `monday`…`sunday`), `ClientKind` (Web, Native) |
| `app/Policies/`               | `WorkoutPolicy` — owner + status checks; `Equipment`/`Category`/`ExercisePolicy` — admin-only catalog mutations |
| `app/Http/Middleware/`        | `EnsureUserIsAdmin` (`admin` alias), `EnsureEmailIsVerified` (`verified` alias, coded 403 for JSON), `NegotiateApiLocale` (api group) |
| `app/Auth/`                   | `JwtUserResolver` — bearer-token resolver behind the `jwt` guard             |
| `app/Rules/`                  | Custom validation rules (`CompletedSetRequiresEffort`, `HeartRateZoneWithinRange`) |
| `apps/web/src/components/ui/` | Reusable UI primitives (shadcn-vue: badge, button, card, empty, input, select, skeleton, sonner, switch, textarea, ...) |
| `apps/web/src/composables/`   | `useApi`, `useApiForm`, `useEnrollment`, `useTranslation` (`t()`), `usePageTitle`, `useActiveRoute`, `useLogout` |
| `apps/web/src/stores/`        | Pinia: `auth` (token + user), `locale` (UI catalogs), `meta` |
| `apps/web/src/router/`        | `routes.js` (named routes mirroring the old Laravel names), `guards.js` (guest/auth/verified/admin) |
| `apps/web/src/utils/`         | `date` (locale-aware formatting), `format` (status display) |
| `lang/`                       | JSON translation files (`en.json`, `ru.json`) for UI strings                 |

## Data Model

[DB diagram →](https://dbdiagram.io/d/workouts-68a1ae421d75ee360ae77ad8)

Core relationships:

```
Program ←M:N→ WorkoutTemplate (pivot: weekday)
Program ←M:N→ User           (pivot: program_user — enrollment, unique per user and program)
WorkoutTemplate ←morph— Activity
Workout      ←morph— Activity
Workout → User
Workout → WorkoutTemplate
Activity → Exercise
Activity ←1:N— Set
Exercise → Equipment
Exercise ←M:N→ Category
User ←M:N→ Role              (pivot: role_user — `Admin` role gates content management)
{Exercise,Equipment,Category,Program,WorkoutTemplate} ←morph— Translation (non-source locales)
```

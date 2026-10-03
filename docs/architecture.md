# Architecture

## Stack

Monorepo — `services/api-php`: Laravel 12 JSON API · `apps/web`: Vue 3 SPA (Composition API, `<script setup>`, vue-router, Pinia) · Tailwind v3 · MySQL · Vite · Docker Compose (nginx `web` image + php-fpm `api` image). The API renders no HTML; every page is the SPA.

## Key Patterns

**Service + Interface DI** — Business logic in service classes (`WorkoutService`), each behind an interface, bound in `AppServiceProvider`.

**Query Builder** — `WorkoutBuilder` extends Eloquent Builder with composable scopes: `ownedBy()`, `withTemplate()`, `withActivitiesCount()`, `latestUpdated()`.

**Polymorphic Activities** — `Activity` morphs to `WorkoutTemplate` or `Workout`. Morph map in `AppServiceProvider`: `workout_template`, `workout`, `exercise`, `category`, `equipment`, `program`.

**API Contract** — `contracts/openapi.yaml` (OpenAPI 3.1) is the source of truth for every client and backend. It is linted in CI and enforced by Schemathesis against a live API (`scripts/contract-test.sh`, `.github/workflows/contract.yml`). TypeScript types for the SPA are generated from it.

**API Error Envelope** — Every non-2xx response under `api/*` is `{ message, code }` (`App\Exceptions\Api\ApiErrorResponse`, wired in `bootstrap/app.php`). Codes: `unauthenticated`, `token_expired`, `forbidden`, `email_unverified`, `not_found`, `conflict`, `too_many_requests`, `validation_failed`, plus explicit ones thrown via `ApiException` (e.g. `in_use` on reference guards). Validation failures add `errors: { field: [{ code, message }] }` where `code` is the failed rule name (`required`, `email`, `unique`, `enum`, custom rule class in snake_case) or `invalid` for manually added messages.

**iOS client generation** — `apps/ios/Packages/WorkoutsAPI` generates a Swift client from `contracts/openapi.yaml` at build time (`swift-openapi-generator` build plugin; the contract is symlinked into the package and nothing generated is committed). `WorkoutsCore` maps the generated types to domain models and references every field the app uses, so removing or renaming a field in the contract breaks the iOS build in CI. Two contract conventions exist for this generator: nullable enums are inlined (`type: [string, 'null']` with the values) and optional object fields are omitted rather than `null`.

**Eloquent Resources** — All API responses go through API Resources (`ProgramResource`, `WorkoutResource`, `ActivityResource`, `SetResource`, `WorkoutTemplateResource`, `UserResource`). Optional object fields (`workout_template`, `in_progress_workout`) are omitted when there is nothing to return, never set to `null`, so generated clients keep them as optional typed fields.

**Form Requests** — Validation via dedicated request classes. Authorization via policies separately.

**Role-Based Authorization** — `User::hasRole()` / `User::isAdmin()` check the `roles` relation. The `admin` middleware alias (`EnsureUserIsAdmin`, registered in `bootstrap/app.php`) gates admin web and API route groups; per-model policies (`EquipmentPolicy`, `CategoryPolicy`, `ExercisePolicy`, `WorkoutTemplatePolicy`, `ProgramPolicy`) provide defense-in-depth. `UserResource` exposes `is_admin`, so the shared `auth.user` prop drives admin-only navigation. Admin write endpoints live under `App\Http\Controllers\Api\Admin` with business logic in `App\Services\Admin` (`WorkoutTemplateService` syncs a template's nested activities and sets; `ProgramService` syncs a program's weekday template assignments — both transactionally).

**Client Bootstrap** — Clients restore their session from the refresh cookie (`POST /api/v1/auth/refresh`), then load `GET /api/v1/me` (`UserResource`, includes `is_admin` and `email_verified`) and the public `GET /api/v1/meta` (locales, theme preferences).

**API Authentication (JWT)** — The API is its own token issuer (`App\Services\Auth\TokenService` behind `TokenServiceInterface`). `POST /api/v1/auth/login` returns a short-lived EdDSA-signed access token (`config('jwt.access_ttl')`, default 15 min) and starts a refresh-token *family* (`refresh_tokens` table, hashed at rest). `POST /api/v1/auth/refresh` rotates the refresh token; presenting an already-rotated token revokes the whole family (reuse detection). Web clients (`client: web`) receive the refresh token only as an httpOnly `SameSite=Strict` cookie scoped to the refresh path and must send `X-Requested-With`; native clients (`client: native`) receive it in the body. Access tokens carry `iss`, `aud`, `sub`, `jti`, `exp`, `auth_time` and `fam` (family id) and are verified by the `jwt` guard (`App\Auth\JwtGuard` + `JwtUserResolver`, the default and only guard) against the public key published at `/.well-known/jwks.json`. Keys come from `JWT_PRIVATE_KEY` / `JWT_PUBLIC_KEY` (`php artisan jwt:keys`). There are no sessions; a 401 on `api/*` carries a `code` (`unauthenticated` or `token_expired`). Password changes and account deletion revoke the user's refresh families. `POST /api/v1/auth/reauth` re-checks the password and reissues an access token with a fresh `auth_time` (step-up). Verification and reset emails link to `config('app.frontend_url')`; `App\Auth\EmailVerificationLink` signs and validates verification links against the named `verification.verify` route, which exists only for URL generation (it forwards to the SPA).

**Internationalization** — `NegotiateApiLocale` sets the locale from `Accept-Language`. Translatable models keep their source text in their own columns and other locales in the polymorphic `translations` table, read through the `HasTranslations` trait. `lang/*.json` holds the backend's own translated strings (labels and messages embedded in responses); UI strings live in each client. Details: [Internationalization](#internationalization).

**Lazy loading is rejected outside production** — `Model::preventLazyLoading()` is enabled in `AppServiceProvider`, so a query that drops an eager load fails loudly instead of running one query per row.

**Soft-deleted exercises** — `Exercise` uses `SoftDeletes`: an admin "delete" **retires** it (removed from catalog/pickers via the global scope) without touching workout history. `Activity::exercise()` is `->withTrashed()`, so historical workouts and templates still resolve a retired exercise's name/effort/difficulty. Reference guards that must see retired rows use `withTrashed()` (e.g. the equipment-in-use delete guard). Templates and programs are hard-deleted (a `Workout` copies its own `name` and owns its activities/sets, so no history is lost); a template can't be deleted while a program uses it (409).

**Catalog Sync** — The shipped catalog (equipment, categories, exercises, workout templates, programs) is defined in PHP data files under `database/catalog/`, each record keyed by a `slug`. `php artisan catalog:sync` (`App\Services\Catalog\CatalogSyncService`, run by every production deploy and by `CatalogSeeder`) validates all files first (`CatalogValidator`; any violation aborts before a write), then applies them in one transaction through the admin services. Rows are matched by `slug`; a row without a slug is adopted by its English name or one of the record's `previous_names`, so ids and workout history survive. The data files win: a managed record (non-null `slug`) is reset to the files on every run and unchanged records are skipped. Records created in the admin UI have no slug and are never touched. Nothing is deleted by omission: removals are listed in `database/catalog/retired.php`, exercises are soft-deleted, and other types are deleted only when nothing references them (otherwise a warning). `--dry-run` reports without writing; `--strict` writes nothing when there are warnings. `slug` is not fillable and not exposed by any resource. Besides structure (unique slugs, both locales, resolvable references, zones 1–5), the validator enforces content rules: every equipment item and category is used by an exercise, a repetition-based exercise has a rest time, and a template set on weight-based equipment carries no weight.

**Seeders** — `DatabaseSeeder` runs `RoleSeeder` and `CatalogSeeder` everywhere, and `UserSeeder` + `WorkoutSeeder` (demo admin and demo workouts) outside production only; both demo seeders throw in production. All seeders are re-runnable. The `Admin` and `User` roles are also inserted by a migration, so an environment that never seeds still has them.

**Two-Axis Measurement** — Sets use `effort_value` (reps/seconds) + `difficulty_value` (nullable). `Equipment.difficulty_unit` (kilograms, pounds, plates, heart_rate_zone, none) controls the load axis; `Exercise.effort_type` (repetitions, duration) controls work. "Bodyweight" equipment (`difficulty_unit = none`) hides the difficulty field. For `heart_rate_zone`, `difficulty_value` is an integer 1–5 (a heart-rate zone) — validated on save by `App\Rules\HeartRateZoneWithinRange` and entered via a 1–5 picker in the set editor; a blank zone stores null. All unit/effort combinations are valid.

## Key Decisions

**Programs and workouts are separate concepts** — Programs and templates are shared catalog data. User workouts are personal copies that can diverge from the original template.

**Bulk workout save** — Workout editing happens client-side and is persisted as a full activities-and-sets payload. The service diffs, validates ownership, and applies changes transactionally.

**Repeat breaks template linkage** — Repeated workouts set `workout_template_id` to `null` so the repeated workout is treated as a user-owned copy, not a live projection of the original template.

**Content is translated by the API, UI strings by the client** — Catalog content is stored once in its source language with per-locale overrides, and the API returns the value for the negotiated locale. UI strings belong to each client (`apps/web/src/i18n/locales`).

## Internationalization

### Locale Resolution

- Supported locales: `en`, `ru`
- User preference is stored in `users.locale`
- API requests: `NegotiateApiLocale` uses only `Accept-Language` (negotiated against `available_locales`, fallback `en`). The stored profile locale is a synced preference the client sends back as the header. Responses carry `Content-Language` and `Vary: Accept-Language`
- `GET /api/v1/meta` lists `available_locales`; the SPA sends its active locale as `Accept-Language`

### Translation Sources

- Source text lives in the `name` / `description` columns of each translatable table, written in `config('app.source_locale')` (English for the shipped catalog); `name` is required
- Other locales live in the polymorphic `translations` table, one row per record, locale and field
- Reads return the requested locale's row and fall back to the source text, so a record with no translation rows reads the same in every locale
- `HasTranslations::createWithTranslations()` creates records; `updateTranslations()` applies edits and removes blank values so reads fall back. Both route the source locale to the columns
- Assigning a translatable attribute (`$exercise->name = ...`) writes the source text
- Translations are auto-eager-loaded, and purged only on a real/force delete, so a soft-deleted record still resolves its name
- Per-locale values (`translations`) are returned only by the admin resources (`AdminExerciseResource`, `AdminCategoryResource`, `AdminProgramResource`, `AdminWorkoutTemplateResource`, `EquipmentResource`); public resources carry the localized value alone
- Admin validation rules for `translations` are built from `available_locales` by `HasTranslationRules`; only the source locale's name is required
- Shipped catalog content carries both locales in the `database/catalog/` data files (see Catalog Sync)
- Backend strings embedded in responses (weekday and status labels, guard messages) live in `lang/en.json` and `lang/ru.json`; UI strings live in `apps/web/src/i18n/locales`
- Validation and auth messages use Laravel language files under `lang/{locale}`

### Model Translation Rules

- `Exercise`, `Category`, `Equipment`, `Program`, and `WorkoutTemplate` are translated through `HasTranslations`
- User content is not re-translated after creation
- Workouts copy the translated template name at creation time

## Directory Guide

Only non-obvious locations listed.

| Path                          | Contains                                                                     |
| ----------------------------- | ---------------------------------------------------------------------------- |
| `apps/web/`                   | The Vue SPA (vue-router, Pinia) consuming `/api/v1`; served by the `web` nginx image |
| `services/api-php/`           | The Laravel API; `docker/nginx/api-locations.conf` is the only path from the SPA to it |
| `docker/nginx/`               | nginx snippets shared by dev and production: API routes, SPA fallback, `/.well-known/apple-app-site-association` for iOS Universal Links (rendered from `APPLE_APP_ID`), security headers |
| `contracts/`                  | `openapi.yaml`, the API contract every client and backend implements       |
| `app/Services/{Domain}/`      | Service class + interface per domain (`Workout/`, `Admin/`, `Auth/`, `Catalog/`) |
| `database/catalog/`           | Catalog data files applied by `catalog:sync`; `retired.php` lists removals  |
| `app/Http/Controllers/Api/Admin/` | Admin catalog CRUD controllers (equipment, categories, exercises)       |
| `app/QueryBuilders/`          | Custom Eloquent builders                                                     |
| `app/Enums/`                  | `WorkoutStatus` (InProgress, Completed), `EffortType` (Repetitions, Duration), `DifficultyUnit` (Kilograms, Pounds, Plates, HeartRateZone, None), `Weekday` (Monday…Sunday), `ClientKind` (Web, Native) |
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
Program ←M:N→ User           (pivot: program_user — enrollment)
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

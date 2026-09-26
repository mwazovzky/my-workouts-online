# Architecture

## Stack

Laravel 12 · Inertia v2 · Vue 3 (Composition API, `<script setup>`) · Tailwind v3 · MySQL · Vite · Docker Compose

## Key Patterns

**Service + Interface DI** — Business logic in service classes (`WorkoutService`), each behind an interface, bound in `AppServiceProvider`.

**Query Builder** — `WorkoutBuilder` extends Eloquent Builder with composable scopes: `ownedBy()`, `withTemplate()`, `withActivitiesCount()`, `latestUpdated()`.

**Polymorphic Activities** — `Activity` morphs to `WorkoutTemplate` or `Workout`. Morph map in `AppServiceProvider`: `workout_template`, `workout`, `exercise`, `category`, `equipment`, `program`.

**Eloquent Resources** — All Inertia responses go through API Resources (`ProgramResource`, `WorkoutResource`, `ActivityResource`, `SetResource`, `WorkoutTemplateResource`, `UserResource`).

**Form Requests** — Validation via dedicated request classes. Authorization via policies separately.

**Role-Based Authorization** — `User::hasRole()` / `User::isAdmin()` check the `roles` relation. The `admin` middleware alias (`EnsureUserIsAdmin`, registered in `bootstrap/app.php`) gates admin web and API route groups; per-model policies (`EquipmentPolicy`, `CategoryPolicy`, `ExercisePolicy`, `WorkoutTemplatePolicy`, `ProgramPolicy`) provide defense-in-depth. `UserResource` exposes `is_admin`, so the shared `auth.user` prop drives admin-only navigation. Admin write endpoints live under `App\Http\Controllers\Api\Admin` with business logic in `App\Services\Admin` (`WorkoutTemplateService` syncs a template's nested activities and sets; `ProgramService` syncs a program's weekday template assignments — both transactionally).

**Deferred Props** — `ProgramShow` defers `workouts` (templates list); `WorkoutShow` defers `activities` (with sets, exercise, equipment, categories).

**Shared Auth** — `auth.user` shared to all pages via `HandleInertiaRequests` + `AppServiceProvider`.

**API Authentication (JWT)** — The API is its own token issuer (`App\Services\Auth\TokenService` behind `TokenServiceInterface`). `POST /api/v1/auth/login` returns a short-lived EdDSA-signed access token (`config('jwt.access_ttl')`, default 15 min) and starts a refresh-token *family* (`refresh_tokens` table, hashed at rest). `POST /api/v1/auth/refresh` rotates the refresh token; presenting an already-rotated token revokes the whole family (reuse detection). Web clients (`client: web`) receive the refresh token only as an httpOnly `SameSite=Strict` cookie scoped to the refresh path and must send `X-Requested-With`; native clients (`client: native`) receive it in the body. Access tokens carry `iss`, `aud`, `sub`, `jti`, `exp`, `auth_time` and `fam` (family id) and are verified by the `jwt` guard (`App\Auth\JwtUserResolver`, registered with `Auth::viaRequest`) against the public key published at `/.well-known/jwks.json`. Keys come from `JWT_PRIVATE_KEY` / `JWT_PUBLIC_KEY` (`php artisan jwt:keys`). During the Inertia-to-SPA transition API routes use `auth:jwt,web`, so the existing session-authenticated frontend keeps working; a 401 on `api/*` carries a `code` (`unauthenticated` or `token_expired`). Password changes and account deletion revoke the user's refresh families. `POST /api/v1/auth/reauth` re-checks the password and reissues an access token with a fresh `auth_time` (step-up). Verification and reset emails link to `config('app.frontend_url')`; `App\Auth\EmailVerificationLink` signs and validates verification links compatibly with the `verification.verify` web route.

**Internationalization** — `SetLocale` middleware sets locale from `User.locale`. `HasTranslations` trait on system models provides polymorphic translations with auto-eager-loading. Translations are purged only on a real/force delete, so a **soft-deleted** record still resolves its name. UI strings in `lang/*.json` are shared through Inertia and consumed via `useTranslation`.

**Soft-deleted exercises** — `Exercise` uses `SoftDeletes`: an admin "delete" **retires** it (removed from catalog/pickers via the global scope) without touching workout history. `Activity::exercise()` is `->withTrashed()`, so historical workouts and templates still resolve a retired exercise's name/effort/difficulty. Reference guards that must see retired rows use `withTrashed()` (e.g. the equipment-in-use delete guard). Templates and programs are hard-deleted (a `Workout` copies its own `name` and owns its activities/sets, so no history is lost); a template can't be deleted while a program uses it (409).

**Two-Axis Measurement** — Sets use `effort_value` (reps/seconds) + `difficulty_value` (nullable). `Equipment.difficulty_unit` (kilograms, pounds, plates, heart_rate_zone, none) controls the load axis; `Exercise.effort_type` (repetitions, duration) controls work. "Bodyweight" equipment (`difficulty_unit = none`) hides the difficulty field. For `heart_rate_zone`, `difficulty_value` is an integer 1–5 (a heart-rate zone) — validated on save by `App\Rules\HeartRateZoneWithinRange` and entered via a 1–5 picker in the set editor; a blank zone stores null. All unit/effort combinations are valid.

## Key Decisions

**Programs and workouts are separate concepts** — Programs and templates are shared catalog data. User workouts are personal copies that can diverge from the original template.

**Bulk workout save** — Workout editing happens client-side and is persisted as a full activities-and-sets payload. The service diffs, validates ownership, and applies changes transactionally.

**Repeat breaks template linkage** — Repeated workouts set `workout_template_id` to `null` so the repeated workout is treated as a user-owned copy, not a live projection of the original template.

**Translation strategy is backend-first** — System content is translated in the database, UI strings are translated from JSON files, and the frontend receives translated values rather than owning a separate i18n data source.

## Internationalization

### Locale Resolution

- Supported locales: `en`, `ru`
- User preference is stored in `users.locale`
- `SetLocale` middleware applies the authenticated user's locale
- `HandleInertiaRequests` shares `locale`, `availableLocales`, and JSON translations with every page

### Translation Sources

- System models use the polymorphic `translations` table
- `HasTranslations::createWithTranslations()` seeds records; `updateTranslations()` applies admin edits and removes blank values so reads fall back to English
- UI strings live in `lang/en.json` and `lang/ru.json`
- Validation and auth messages use Laravel language files under `lang/{locale}`

### Model Translation Rules

- `Exercise`, `Category`, `Equipment`, `Program`, and `WorkoutTemplate` are translated through `HasTranslations`
- User content is not re-translated after creation
- Workouts copy the translated template name at creation time

## Directory Guide

Only non-obvious locations listed.

| Path                          | Contains                                                                     |
| ----------------------------- | ---------------------------------------------------------------------------- |
| `app/Services/{Domain}/`      | Service class + interface per domain (`Workout/`, `Admin/`, `Auth/`)         |
| `app/Http/Controllers/Api/Admin/` | Admin catalog CRUD controllers (equipment, categories, exercises)       |
| `app/QueryBuilders/`          | Custom Eloquent builders                                                     |
| `app/Enums/`                  | `WorkoutStatus` (InProgress, Completed), `EffortType` (Repetitions, Duration), `DifficultyUnit` (Kilograms, Pounds, Plates, HeartRateZone, None), `Weekday` (Monday…Sunday), `ClientKind` (Web, Native) |
| `app/Policies/`               | `WorkoutPolicy` — owner + status checks; `Equipment`/`Category`/`ExercisePolicy` — admin-only catalog mutations |
| `app/Http/Middleware/`        | `EnsureUserIsAdmin` (`admin` alias) — 403s non-admins                        |
| `app/Auth/`                   | `JwtUserResolver` — bearer-token resolver behind the `jwt` guard             |
| `app/Rules/`                  | Custom validation rules (`CompletedSetRequiresEffort`, `HeartRateZoneWithinRange`) |
| `resources/js/Components/ui/` | Reusable UI primitives (alert, alert-dialog, avatar, badge, button, card, empty, input, select, separator, sheet, skeleton, sonner, switch, table, textarea, tooltip) |
| `resources/js/composables/`   | `useEnrollment` — enrollment state + toggle; `useTranslation` — `t()` helper |
| `resources/js/utils/`         | `date` (locale-aware formatting), `format` (status display), `navigation` (route helpers) |
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
{Exercise,Equipment,Category,Program,WorkoutTemplate} ←morph— Translation
```

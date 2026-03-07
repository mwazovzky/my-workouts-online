# Architecture

## Stack

Laravel 12 · Inertia v2 · Vue 3 (Composition API, `<script setup>`) · Tailwind v3 · MySQL · Vite · Docker Compose

## Key Patterns

**Service + Interface DI** — Business logic in service classes (`WorkoutService`), each behind an interface, bound in `AppServiceProvider`.

**Query Builder** — `WorkoutBuilder` extends Eloquent Builder with composable scopes: `ownedBy()`, `withTemplate()`, `withActivitiesCount()`, `latestUpdated()`.

**Polymorphic Activities** — `Activity` morphs to `WorkoutTemplate` or `Workout`. Morph map in `AppServiceProvider`: `workout_template`, `workout`, `exercise`, `category`, `equipment`, `program`.

**Eloquent Resources** — All Inertia responses go through API Resources (`ProgramResource`, `WorkoutResource`, `ActivityResource`, `SetResource`, `WorkoutTemplateResource`, `UserResource`).

**Form Requests** — Validation via dedicated request classes. Authorization via policies separately.

**Deferred Props** — `ProgramShow` defers `workouts` (templates list); `WorkoutShow` defers `activities` (with sets, exercise, equipment, categories).

**Shared Auth** — `auth.user` shared to all pages via `HandleInertiaRequests` + `AppServiceProvider`.

**Internationalization** — `SetLocale` middleware sets locale from `User.locale`. `HasTranslations` trait on system models provides polymorphic translations with auto-eager-loading. UI strings in `lang/*.json` are shared through Inertia and consumed via `useTranslation`.

**Two-Axis Measurement** — Sets use `effort_value` (reps/seconds) + `difficulty_value` (weight/plates, nullable). `Equipment.difficulty_unit` (kilograms, pounds, plates, none) controls load; `Exercise.effort_type` (repetitions, duration) controls work. "Bodyweight" equipment (`difficulty_unit = none`) eliminates nullable FKs. All 4x2 combinations valid.

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
| `app/Services/{Domain}/`      | Service class + interface per domain                                         |
| `app/QueryBuilders/`          | Custom Eloquent builders                                                     |
| `app/Enums/`                  | `WorkoutStatus` (InProgress, Completed), `EffortType` (Repetitions, Duration), `DifficultyUnit` (Kilograms, Pounds, Plates, None) |
| `app/Policies/`               | `WorkoutPolicy` — owner + status checks for workout mutations                |
| `app/Rules/`                  | Custom validation rules (`CompletedSetRequiresEffort`)                       |
| `resources/js/Components/ui/` | Reusable UI primitives (alert, alert-dialog, avatar, badge, button, card, empty, input, separator, sheet, skeleton, sonner, switch, table, tooltip) |
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
User ←M:N→ Role              (unused — future RBAC)
{Exercise,Equipment,Category,Program,WorkoutTemplate} ←morph— Translation
```

# Architecture

## Stack

Laravel 12 · Inertia v2 · Vue 3 (Composition API, `<script setup>`) · Tailwind v3 · MySQL · Vite · Docker Compose

## Key Patterns

**Service + Interface DI** — Business logic in service classes (`WorkoutService`), each behind an interface. Bound in `AppServiceProvider`.

**Query Builder** — `WorkoutBuilder` extends Eloquent Builder with composable scopes: `ownedBy()`, `withTemplate()`, `withActivitiesCount()`, `latestUpdated()`.

**Polymorphic Activities** — `Activity` uses a morph relation (`workout_type` / `workout_id`) to belong to either `WorkoutTemplate` or `Workout`. Morph map registered in `AppServiceProvider`: `workout_template`, `workout`.

**Eloquent Resources** — All Inertia responses go through API Resources (`ProgramResource`, `WorkoutResource`, `ActivityResource`, `SetResource`, `WorkoutTemplateResource`, `UserResource`).

**Form Requests** — Validation via dedicated request classes (`WorkoutStoreRequest`, `WorkoutSaveRequest`). Authorization handled separately via policies.

**Inertia Deferred Props** — `ProgramShow` and `WorkoutShow` use deferred props for lazy-loaded relationship data.

**Shared Auth** — `auth.user` shared to all Inertia pages via `HandleInertiaRequests` middleware + `AppServiceProvider`.

## Directory Guide

Only non-obvious locations listed.

| Path                          | Contains                                                                     |
| ----------------------------- | ---------------------------------------------------------------------------- |
| `app/Services/{Domain}/`      | Service class + interface per domain                                         |
| `app/QueryBuilders/`          | Custom Eloquent builders                                                     |
| `app/Enums/`                  | `WorkoutStatus` (InProgress, Completed)                                      |
| `app/Policies/`               | `WorkoutPolicy`                                                              |
| `resources/js/Components/ui/` | Reusable UI primitives (badge, button, card, empty, input, skeleton, switch) |
| `resources/js/composables/`   | `useEnrollment` — enrollment state + toggle                                  |
| `resources/js/utils/`         | `date` (formatting), `navigation` (route helpers)                            |

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
```

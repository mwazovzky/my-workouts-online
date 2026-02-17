# Pages & Routes

## Pages

| Page                  | Path                                                                                                       | Auth       | Purpose                                                                                                   |
| --------------------- | ---------------------------------------------------------------------------------------------------------- | ---------- | --------------------------------------------------------------------------------------------------------- |
| `Welcome`             | `/`                                                                                                        | No         | Public landing page                                                                                       |
| `Dashboard`           | `/dashboard`                                                                                               | Yes        | Authenticated home                                                                                        |
| `ProgramIndex`        | `/programs`                                                                                                | Yes        | Browse all programs                                                                                       |
| `ProgramShow`         | `/programs/{id}`                                                                                           | Yes        | View program details and workout templates by weekday                                                     |
| `WorkoutTemplateShow` | `/workout-templates/{id}`                                                                                  | Yes        | View a single workout template                                                                            |
| `WorkoutIndex`        | `/workouts`                                                                                                | Yes        | User's workout history (paginated, owner-scoped)                                                          |
| `WorkoutShow`         | `/workouts/{id}`                                                                                           | Yes        | View a completed/in-progress workout (owner-scoped)                                                       |
| `WorkoutEdit`         | `/workouts/{id}/edit`                                                                                      | Yes        | Edit an in-progress workout — manage activities and sets locally, save all changes at once (owner-scoped) |
| Auth pages (6)        | `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`, `/confirm-password` | Guest/Auth | Standard Breeze authentication flow                                                                       |
| `Profile/Edit`        | `/profile`                                                                                                 | Yes        | Update name/email, change password, delete account                                                        |

## Mutation Endpoints

| Action                      | Method | Path                           | Route Name          | Guard                                                           |
| --------------------------- | ------ | ------------------------------ | ------------------- | --------------------------------------------------------------- |
| Enroll in program           | POST   | `/programs/{program}/enroll`   | `programs.enroll`   | Auth only                                                       |
| Start workout from template | POST   | `/workouts`                    | `workouts.store`    | Auth + FormRequest                                              |
| Save workout (bulk)         | PATCH  | `/workouts/{workout}/save`     | `workouts.save`     | `WorkoutPolicy@save` — owner + status must be `in_progress`     |
| Complete workout            | POST   | `/workouts/{workout}/complete` | `workouts.complete` | `WorkoutPolicy@complete` — owner + status must be `in_progress` |
| Repeat workout              | POST   | `/workouts/{workout}/repeat`   | `workouts.repeat`   | `WorkoutPolicy@repeat` — owner + status must be `completed`     |
| Delete workout              | DELETE | `/workouts/{workout}`          | `workouts.destroy`  | `WorkoutPolicy@delete` — owner only                             |
| Update profile              | PATCH  | `/profile`                     | `profile.update`    | Auth only                                                       |
| Update language             | PATCH  | `/profile/locale`              | `profile.locale`    | Auth only                                                       |
| Delete account              | DELETE | `/profile`                     | `profile.destroy`   | Auth only                                                       |

## API

Minimal. Single endpoint: `GET /api/user` (Sanctum-authenticated) — returns the current user.

## Access Rules

- **Owner-scoped reads**: Workout pages (index, show, edit) query through `WorkoutBuilder::ownedBy($user)`. Non-owners see 404.
- **Policy-guarded mutations**: `WorkoutPolicy` checks `user_id` match and enforces status prerequisites.
- **Programs**: No ownership — all authenticated users can browse and enroll.
- **Roles**: `Role` model exists (seeded) but is not consumed by any gate or middleware. Scaffolding for future use.

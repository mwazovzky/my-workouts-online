# Pages & Routes

## Pages

| Page | Path | Auth | Purpose |
|---|---|---|---|
| `Welcome` | `/` | No | Public landing page |
| `Dashboard` | `/dashboard` | Yes | Authenticated home |
| `ProgramIndex` | `/programs` | Yes | Browse all programs |
| `ProgramShow` | `/programs/{id}` | Yes | View program details and workout templates by weekday |
| `WorkoutTemplateShow` | `/workout-templates/{id}` | Yes | View a single workout template |
| `WorkoutLogIndex` | `/workout-logs` | Yes | User's workout log history (paginated, owner-scoped) |
| `WorkoutLogShow` | `/workout-logs/{id}` | Yes | View a completed/in-progress workout log (owner-scoped) |
| `WorkoutLogEdit` | `/workout-logs/{id}/edit` | Yes | Edit an in-progress log — update sets, delete activities (owner-scoped) |
| Auth pages (6) | `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`, `/confirm-password` | Guest/Auth | Standard Breeze authentication flow |
| `Profile/Edit` | `/profile` | Yes | Update name/email, change password, delete account |

## Mutation Endpoints

| Action | Method | Path | Route Name | Guard |
|---|---|---|---|---|
| Enroll in program | POST | `/programs/{program}/enroll` | `programs.enroll` | Auth only |
| Start workout from template | POST | `/workout-logs` | `workout.logs.store` | Auth + FormRequest |
| Complete workout | POST | `/workout-logs/{workoutLog}/complete` | `workout.logs.complete` | `WorkoutLogPolicy@complete` — owner + status must be `in_progress` |
| Repeat workout | POST | `/workout-logs/{workoutLog}/repeat` | `workout.logs.repeat` | `WorkoutLogPolicy@repeat` — owner + status must be `completed` |
| Delete workout log | DELETE | `/workout-logs/{workoutLog}` | `workout.logs.destroy` | `WorkoutLogPolicy@delete` — owner only |
| Update activity sets | PATCH | `/activities/{activity}` | `activities.update` | `ActivityPolicy@update` — owner, workout-log activities only |
| Delete activity | DELETE | `/activities/{activity}` | `activities.destroy` | `ActivityPolicy@delete` — owner, workout-log activities only |
| Update profile | PATCH | `/profile` | `profile.update` | Auth only |
| Delete account | DELETE | `/profile` | `profile.destroy` | Auth only |

## API

Minimal. Single endpoint: `GET /api/user` (Sanctum-authenticated) — returns the current user.

## Access Rules

- **Owner-scoped reads**: Workout log pages (index, show, edit) query through `WorkoutLogBuilder::ownedBy($user)`. Non-owners see 404.
- **Policy-guarded mutations**: `WorkoutLogPolicy` checks `user_id` match and enforces status prerequisites. `ActivityPolicy` checks ownership by traversing `activity → workout_log → user_id` and rejects template activities.
- **Programs**: No ownership — all authenticated users can browse and enroll.
- **Roles**: `Role` model exists (seeded) but is not consumed by any gate or middleware. Scaffolding for future use.

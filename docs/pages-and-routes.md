# Pages & Routes

Lookup reference for the app surface area. Detailed behavior belongs in feature docs.

## Pages

| Page                  | Path                      | Access          | Owning Feature  | Notes                                                                    |
| --------------------- | ------------------------- | --------------- | --------------- | ------------------------------------------------------------------------ |
| `Welcome`             | `/`                       | Public          | Landing         | Public landing page; authenticated users are redirected to the dashboard |
| `Dashboard`           | `/dashboard`              | Auth + verified | Landing         | Authenticated home with upcoming workouts, summary, and recent history   |
| `ProgramIndex`        | `/programs`               | Auth + verified | Programs        | Browse all programs                                                      |
| `ProgramShow`         | `/programs/{id}`          | Auth + verified | Programs        | View one program and its templates                                       |
| `WorkoutTemplateShow` | `/workout-templates/{id}` | Auth + verified | Programs        | View a single workout template                                           |
| `WorkoutIndex`        | `/workouts`               | Auth + verified | Workout Logging | Owner-scoped workout history                                             |
| `WorkoutShow`         | `/workouts/{id}`          | Auth + verified | Workout Logging | Owner-scoped workout details                                             |
| `WorkoutEdit`         | `/workouts/{id}/edit`     | Auth + verified | Workout Logging | Edit an in-progress workout                                              |
| `Register`            | `/register`               | Guest           | Auth & Profile  | Breeze registration page                                                 |
| `Login`               | `/login`                  | Guest           | Auth & Profile  | Breeze login page                                                        |
| `Forgot Password`     | `/forgot-password`        | Guest           | Auth & Profile  | Request password reset link                                              |
| `Reset Password`      | `/reset-password/{token}` | Guest           | Auth & Profile  | Enter a new password                                                     |
| `Verify Email`        | `/verify-email`           | Auth            | Auth & Profile  | Prompt for email verification                                            |
| `Confirm Password`    | `/confirm-password`       | Auth            | Auth & Profile  | Confirm password for sensitive actions                                   |
| `Profile/Edit`        | `/profile`                | Auth            | Auth & Profile  | Update profile, password, locale, theme, or delete account               |
| `Admin/Index`         | `/admin`                  | Auth + verified + admin | Admin   | Admin section landing with links to each catalog                         |
| `Admin/EquipmentIndex`| `/admin/equipment`        | Auth + verified + admin | Admin   | List, create, edit, delete equipment                                     |
| `Admin/CategoryIndex` | `/admin/categories`       | Auth + verified + admin | Admin   | List, create, edit, delete categories                                    |
| `Admin/ExerciseIndex` | `/admin/exercises`        | Auth + verified + admin | Admin   | List, create, edit, delete exercises                                     |
| `Admin/WorkoutTemplateIndex` | `/admin/workout-templates` | Auth + verified + admin | Admin | Build workout templates with activities and sets                     |
| `Admin/ProgramIndex`  | `/admin/programs`         | Auth + verified + admin | Admin   | Assemble programs and schedule templates by weekday                      |

Each catalog resource also has dedicated create and edit pages — `Admin/{Resource}Create` at `/admin/{resource}/create` (route `admin.{resource}.create`) and `Admin/{Resource}Edit` at `/admin/{resource}/{id}/edit` (route `admin.{resource}.edit`) — that host the shared `Components/Admin/*Form` component. Edit pages load the record from the admin REST API on mount.

## Endpoints

| Action                      | Method   | Path                               | Route Name            | Access                    | Owning Feature  |
| --------------------------- | -------- | ---------------------------------- | --------------------- | ------------------------- | --------------- |
| Enroll in program           | `POST`   | `/programs/{program}/enroll`       | `programs.enroll`     | Auth + verified           | Programs        |
| Start workout from template | `POST`   | `/workouts`                        | `workouts.store`      | Auth + verified           | Workout Logging |
| Save workout                | `PATCH`  | `/workouts/{workout}/save`         | `workouts.save`       | Auth + verified + policy  | Workout Logging |
| Complete workout            | `POST`   | `/workouts/{workout}/complete`     | `workouts.complete`   | Auth + verified + policy  | Workout Logging |
| Repeat workout              | `POST`   | `/workouts/{workout}/repeat`       | `workouts.repeat`     | Auth + verified + policy  | Workout Logging |
| Delete workout              | `DELETE` | `/workouts/{workout}`              | `workouts.destroy`    | Auth + verified + policy  | Workout Logging |
| Register                    | `POST`   | `/register`                        | —                     | Guest                     | Auth & Profile  |
| Login                       | `POST`   | `/login`                           | —                     | Guest                     | Auth & Profile  |
| Send password reset link    | `POST`   | `/forgot-password`                 | `password.email`      | Guest                     | Auth & Profile  |
| Reset password              | `POST`   | `/reset-password`                  | `password.store`      | Guest                     | Auth & Profile  |
| Verify email                | `GET`    | `/verify-email/{id}/{hash}`        | `verification.verify` | Auth + signed + throttled | Auth & Profile  |
| Send verification email     | `POST`   | `/email/verification-notification` | `verification.send`   | Auth + throttled          | Auth & Profile  |
| Confirm password            | `POST`   | `/confirm-password`                | —                     | Auth                      | Auth & Profile  |
| Update password             | `PUT`    | `/password`                        | `password.update`     | Auth                      | Auth & Profile  |
| Logout                      | `POST`   | `/logout`                          | `logout`              | Auth                      | Auth & Profile  |
| Update profile              | `PATCH`  | `/profile`                         | `profile.update`      | Auth                      | Auth & Profile  |
| Update guest locale         | `PATCH`  | `/locale`                          | `locale.update`       | Public                    | Landing         |
| Update language             | `PATCH`  | `/profile/locale`                  | `profile.locale`      | Auth                      | Auth & Profile  |
| Update theme                | `PATCH`  | `/profile/theme`                   | `profile.theme`       | Auth                      | Auth & Profile  |
| Delete account              | `DELETE` | `/profile`                         | `profile.destroy`     | Auth                      | Auth & Profile  |
| Health check                | `GET`    | `/health`                          | `health`              | Public                    | Operations      |
| Readiness check             | `GET`    | `/health/ready`                    | `health.ready`        | Public                    | Operations      |

## API

The SPA consumes a versioned REST API under `/api/v1`. Protected routes accept a JWT bearer token (`auth:jwt`) or, during the Inertia transition, the web session. Key groups: auth, programs, workouts, workout-templates, dashboard, profile, and the admin catalog below. Route names are prefixed `api.v1.`. The full contract lives in [`contracts/openapi.yaml`](../contracts/openapi.yaml).

| Action              | Method   | Path                                    | Route Name                       | Access                   | Owning Feature |
| ------------------- | -------- | --------------------------------------- | -------------------------------- | ------------------------ | -------------- |
| Register            | `POST`   | `/api/v1/auth/register`                 | `api.v1.auth.register`           | Guest, throttled         | Auth & Profile |
| Login (issue tokens)| `POST`   | `/api/v1/auth/login`                    | `api.v1.auth.login`              | Guest, throttled         | Auth & Profile |
| Refresh tokens      | `POST`   | `/api/v1/auth/refresh`                  | `api.v1.auth.refresh`            | Refresh token (cookie or body) | Auth & Profile |
| Logout              | `POST`   | `/api/v1/auth/logout`                   | `api.v1.auth.logout`             | Auth                     | Auth & Profile |
| Re-authenticate     | `POST`   | `/api/v1/auth/reauth`                   | `api.v1.auth.reauth`             | Auth (bearer), throttled | Auth & Profile |
| Forgot password     | `POST`   | `/api/v1/auth/password/forgot`          | `api.v1.auth.password.forgot`    | Guest, throttled         | Auth & Profile |
| Reset password      | `POST`   | `/api/v1/auth/password/reset`           | `api.v1.auth.password.reset`     | Guest, throttled         | Auth & Profile |
| Verify email        | `POST`   | `/api/v1/auth/email/verify`             | `api.v1.auth.email.verify`       | Auth, throttled          | Auth & Profile |
| Resend verification | `POST`   | `/api/v1/auth/email/resend`             | `api.v1.auth.email.resend`       | Auth, throttled          | Auth & Profile |
| Signing keys        | `GET`    | `/.well-known/jwks.json`                | `jwks`                           | Public                   | Auth & Profile |
| Current user        | `GET`    | `/api/v1/me`                            | `api.v1.me`                      | Auth                     | Auth & Profile |
| App options         | `GET`    | `/api/v1/meta`                          | `api.v1.meta`                    | Public                   | Auth & Profile |


| Action              | Method   | Path                                    | Route Name                       | Access                   | Owning Feature |
| ------------------- | -------- | --------------------------------------- | -------------------------------- | ------------------------ | -------------- |
| List exercises      | `GET`    | `/api/v1/exercises`                     | `api.v1.exercises.index`         | Auth + verified          | Workout Logging |
| List equipment      | `GET`    | `/api/v1/admin/equipment`               | `api.v1.admin.equipment.index`   | Auth + verified + admin  | Admin          |
| Show equipment      | `GET`    | `/api/v1/admin/equipment/{equipment}`   | `api.v1.admin.equipment.show`    | Auth + verified + admin  | Admin          |
| Create equipment    | `POST`   | `/api/v1/admin/equipment`               | `api.v1.admin.equipment.store`   | Auth + verified + admin  | Admin          |
| Update equipment    | `PUT`    | `/api/v1/admin/equipment/{equipment}`   | `api.v1.admin.equipment.update`  | Auth + verified + admin  | Admin          |
| Delete equipment    | `DELETE` | `/api/v1/admin/equipment/{equipment}`   | `api.v1.admin.equipment.destroy` | Auth + verified + admin  | Admin          |
| List categories     | `GET`    | `/api/v1/admin/categories`              | `api.v1.admin.categories.index`  | Auth + verified + admin  | Admin          |
| Show category       | `GET`    | `/api/v1/admin/categories/{category}`   | `api.v1.admin.categories.show`   | Auth + verified + admin  | Admin          |
| Create category     | `POST`   | `/api/v1/admin/categories`              | `api.v1.admin.categories.store`  | Auth + verified + admin  | Admin          |
| Update category     | `PUT`    | `/api/v1/admin/categories/{category}`   | `api.v1.admin.categories.update` | Auth + verified + admin  | Admin          |
| Delete category     | `DELETE` | `/api/v1/admin/categories/{category}`   | `api.v1.admin.categories.destroy`| Auth + verified + admin  | Admin          |
| List exercises      | `GET`    | `/api/v1/admin/exercises`               | `api.v1.admin.exercises.index`   | Auth + verified + admin  | Admin          |
| Show exercise       | `GET`    | `/api/v1/admin/exercises/{exercise}`    | `api.v1.admin.exercises.show`    | Auth + verified + admin  | Admin          |
| Create exercise     | `POST`   | `/api/v1/admin/exercises`               | `api.v1.admin.exercises.store`   | Auth + verified + admin  | Admin          |
| Update exercise     | `PUT`    | `/api/v1/admin/exercises/{exercise}`    | `api.v1.admin.exercises.update`  | Auth + verified + admin  | Admin          |
| Delete exercise     | `DELETE` | `/api/v1/admin/exercises/{exercise}`    | `api.v1.admin.exercises.destroy` | Auth + verified + admin  | Admin          |
| List templates      | `GET`    | `/api/v1/admin/workout-templates`       | `api.v1.admin.workout-templates.index`   | Auth + verified + admin | Admin |
| Show template       | `GET`    | `/api/v1/admin/workout-templates/{workout_template}` | `api.v1.admin.workout-templates.show` | Auth + verified + admin | Admin |
| Create template     | `POST`   | `/api/v1/admin/workout-templates`       | `api.v1.admin.workout-templates.store`   | Auth + verified + admin | Admin |
| Update template     | `PUT`    | `/api/v1/admin/workout-templates/{workout_template}` | `api.v1.admin.workout-templates.update` | Auth + verified + admin | Admin |
| Delete template     | `DELETE` | `/api/v1/admin/workout-templates/{workout_template}` | `api.v1.admin.workout-templates.destroy` | Auth + verified + admin | Admin |
| List programs (admin) | `GET`  | `/api/v1/admin/programs`                | `api.v1.admin.programs.index`    | Auth + verified + admin  | Admin          |
| Show program (admin)  | `GET`  | `/api/v1/admin/programs/{program}`      | `api.v1.admin.programs.show`     | Auth + verified + admin  | Admin          |
| Create program      | `POST`   | `/api/v1/admin/programs`                | `api.v1.admin.programs.store`    | Auth + verified + admin  | Admin          |
| Update program      | `PUT`    | `/api/v1/admin/programs/{program}`      | `api.v1.admin.programs.update`   | Auth + verified + admin  | Admin          |
| Delete program      | `DELETE` | `/api/v1/admin/programs/{program}`      | `api.v1.admin.programs.destroy`  | Auth + verified + admin  | Admin          |

## Related

- [Product Overview](product.md)
- [Programs](features/programs.md)
- [Workout Logging](features/workout-logging.md)
- [Auth & Profile](features/auth-and-profile.md)
- [Admin Content Management](features/admin-content-management.md)

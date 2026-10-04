# Pages & Routes

Lookup reference for the app surface area. Detailed behavior belongs in feature docs.

## Pages (SPA, `apps/web`)

Route names mirror the former Laravel route names (`apps/web/src/router/routes.js`). Access is enforced by route guards: `guest` (signed out only), `auth`, `verified`, `admin`.

| Page                  | Path                      | Access          | Owning Feature  | Notes                                                                    |
| --------------------- | ------------------------- | --------------- | --------------- | ------------------------------------------------------------------------ |
| `Welcome`             | `/`                       | Guest           | Landing         | Public landing page; signed-in users are redirected to the dashboard     |
| `Dashboard`           | `/dashboard`              | Verified        | Landing         | Authenticated home with upcoming workouts, summary, and recent history   |
| `ProgramIndex`        | `/programs`               | Verified        | Programs        | Browse all programs                                                      |
| `ProgramShow`         | `/programs/{id}`          | Verified        | Programs        | View one program and its templates                                       |
| `WorkoutTemplateShow` | `/workout-templates/{id}` | Verified        | Programs        | View a single workout template                                           |
| `WorkoutIndex`        | `/workouts`               | Verified        | Workout Logging | Owner-scoped workout history                                             |
| `WorkoutShow`         | `/workouts/{id}`          | Verified        | Workout Logging | Owner-scoped workout details                                             |
| `WorkoutEdit`         | `/workouts/{id}/edit`     | Verified        | Workout Logging | Edit an in-progress workout (unsaved-changes guard)                      |
| `About`               | `/about`                  | Verified        | Landing         | App version (`VITE_APP_VERSION`)                                         |
| `auth/Register`       | `/register`               | Guest           | Auth & Profile  | Registration, then the verify-email notice                               |
| `auth/Login`          | `/login`                  | Guest           | Auth & Profile  | Honours `?redirect=`; shows a banner after `?reset=1`                    |
| `auth/ForgotPassword` | `/forgot-password`        | Guest           | Auth & Profile  | Request password reset link                                              |
| `auth/ResetPassword`  | `/reset-password/{token}` | Guest           | Auth & Profile  | Enter a new password (`?email=` prefilled)                               |
| `auth/VerifyEmail`    | `/verify-email`           | Auth            | Auth & Profile  | Verification notice with resend                                          |
| `auth/VerifyEmailLink`| `/verify-email/{id}/{hash}` | Auth          | Auth & Profile  | Target of the emailed link; posts the signed parameters to the API       |
| `auth/ConfirmPassword`| `/confirm-password`       | Auth            | Auth & Profile  | Step-up re-authentication, returns to `?redirect=`                       |
| `Profile/Edit`        | `/profile`                | Auth            | Auth & Profile  | Update profile, password, locale, theme, or delete account               |
| `Admin/Index`         | `/admin`                  | Admin           | Admin           | Admin section landing with links to each catalog                         |
| `Admin/EquipmentIndex`| `/admin/equipment`        | Admin           | Admin           | List, create, edit, delete equipment                                     |
| `Admin/CategoryIndex` | `/admin/categories`       | Admin           | Admin           | List, create, edit, delete categories                                    |
| `Admin/ExerciseIndex` | `/admin/exercises`        | Admin           | Admin           | List, create, edit, delete exercises                                     |
| `Admin/WorkoutTemplateIndex` | `/admin/workout-templates` | Admin    | Admin           | Build workout templates with activities and sets                         |
| `Admin/ProgramIndex`  | `/admin/programs`         | Admin           | Admin           | Assemble programs and schedule templates by weekday                      |

Each catalog resource also has `/admin/{resource}/create` (`admin.{resource}.create`) and `/admin/{resource}/{id}/edit` (`admin.{resource}.edit`) pages hosting the shared `components/Admin/*Form` component. Edit pages load the record from the admin API on mount.

## iOS Screens (`apps/ios`)

| Screen | Access | Backed by |
| --- | --- | --- |
| Welcome | guest | — |
| Log in | guest | `POST /api/v1/auth/login` |
| Register | guest | `POST /api/v1/auth/register` |
| Forgot password | guest | `POST /api/v1/auth/password/forgot` |
| Verify email gate | auth, unverified | `GET /api/v1/me`, `POST /api/v1/auth/email/resend` |
| Verification link (Universal Link `/verify-email/{id}/{hash}`) | auth, same user | `POST /api/v1/auth/email/verify`, `GET /api/v1/me` |
| Reset password sheet (Universal Link `/reset-password/{token}`) | any | `POST /api/v1/auth/password/reset` |
| Dashboard tab | verified | `GET /api/v1/dashboard`, `POST /api/v1/workouts` (start from the schedule) |
| Programs tab | verified | `GET /api/v1/programs`, `POST /api/v1/programs/{program}/enroll` |
| Program detail | verified | `GET /api/v1/programs/{program}`, `POST /api/v1/programs/{program}/enroll` |
| Workout template detail | verified | `GET /api/v1/workout-templates/{id}`, `POST /api/v1/workouts` (Start Workout) |
| Workout editor | verified, owner, in progress | `GET /api/v1/workouts/{workout}`, `PATCH /api/v1/workouts/{workout}/save`, `POST /api/v1/workouts/{workout}/complete`, `GET /api/v1/exercises` (Add activity) |
| Workouts, Profile tabs | verified | placeholders until stories 11 and 12; Profile offers log out |

The root view picks the screen from the session state (`signedOut`, `unverified`, `ready`), mirroring the SPA's `guest` / `auth` / `verified` guards. Signed in, a four-tab shell (Dashboard, Programs, Workouts, Profile) mirrors the web bottom navigation; each tab keeps its own navigation stack, and admins see the same four tabs.

## Non-API endpoints (Laravel, outside `/api`)

| Action                      | Method   | Path                               | Route Name            | Access  | Owning Feature |
| --------------------------- | -------- | ---------------------------------- | --------------------- | ------- | -------------- |
| Health check                | `GET`    | `/health`                          | `health`              | Public  | Operations     |
| Readiness check             | `GET`    | `/health/ready`                    | `health.ready`        | Public  | Operations     |
| Framework health            | `GET`    | `/up`                              | —                     | Public  | Operations     |
| Signing keys                | `GET`    | `/.well-known/jwks.json`           | `jwks`                | Public  | Auth & Profile |
| Verification link (forwarder) | `GET`  | `/verify-email/{id}/{hash}`        | `verification.verify` | Public  | Auth & Profile |

nginx routes only these paths and `/api/*` to php-fpm; everything else is served as the SPA.

## API

The SPA consumes a versioned REST API under `/api/v1`. Protected routes require a JWT bearer token (`auth:jwt`). Key groups: auth, programs, workouts, workout-templates, dashboard, profile, and the admin catalog below. Route names are prefixed `api.v1.`. Clients send `Accept-Language`; responses declare `Content-Language`. Errors use the `{ message, code }` envelope and validation errors `{ errors: { field: [{ code, message }] } }`. The full contract lives in [`contracts/openapi.yaml`](../contracts/openapi.yaml).

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

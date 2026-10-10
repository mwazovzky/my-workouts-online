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
| `WorkoutEdit`         | `/workouts/{id}/edit`     | Verified        | Workout Logging | Edit an in-progress workout (unsaved-changes guard; reload prompt when the workout changed elsewhere) |
| `About`               | `/about`                  | Verified        | Landing         | App version (`VITE_APP_VERSION`)                                         |
| `Privacy`             | `/privacy`                | Public          | Landing         | Privacy policy; linked from the guest and authenticated layout footers, Welcome and the iOS Profile tab; signed-in users keep the app navigation |
| `Support`             | `/support`                | Public          | Landing         | Support contact (`support@my-workouts.online`); linked like Privacy       |
| `auth/Register`       | `/register`               | Guest           | Auth & Profile  | Registration, then the verify-email notice                               |
| `auth/Login`          | `/login`                  | Guest           | Auth & Profile  | Honours `?redirect=`; banner after `?reset=1`; 401 detail under email, 429 lockout countdown |
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

Access values: Guest (signed-in users are redirected), Auth, Verified, Admin, and Public (reachable signed in or out).

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
| Programs tab | verified | `GET /api/v1/programs`, `POST /api/v1/programs/{programId}/enroll` |
| Program detail | verified | `GET /api/v1/programs/{programId}`, `POST /api/v1/programs/{programId}/enroll` |
| Workout template detail | verified | `GET /api/v1/workout-templates/{workoutTemplateId}`, `POST /api/v1/workouts` (Start Workout) |
| Workout editor | verified, owner, in progress | `GET /api/v1/workouts/{workoutId}`, `PUT /api/v1/workouts/{workoutId}/activities` (`If-Match`), `POST /api/v1/workouts/{workoutId}/complete`, `GET /api/v1/exercises` (Add activity) |
| Workouts tab | verified | `GET /api/v1/workouts?page=N&per_page=M`, `DELETE /api/v1/workouts/{workoutId}` |
| Workout detail | verified | `GET /api/v1/workouts/{workoutId}`, `POST /api/v1/workouts/{workoutId}/repeat` |
| Profile tab | auth | `PATCH /api/v1/me` (name and email, theme, locale sync), `PUT /api/v1/me/password`, `DELETE /api/v1/me`, `POST /api/v1/auth/email/resend`, `POST /api/v1/auth/logout` |

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

## Webhooks (outside the client contract)

| Action                 | Method | Path                   | Route Name            | Access                  | Owning Feature |
| ---------------------- | ------ | ---------------------- | --------------------- | ----------------------- | -------------- |
| Resend events          | `POST` | `/api/webhooks/resend` | `api.webhooks.resend` | Resend signature (Svix) | Operations     |

Only `email.received` is acted on: it queues `ForwardInboundEmail`, which relays the email to `MAIL_INBOUND_FORWARD_TO`. Setup is in [deployment](deployment.md#support-inbox).

## API

The SPA consumes a versioned REST API under `/api/v1`. Protected routes require a JWT bearer token (`auth:jwt`). Key groups: auth, the current user (`/me`), programs, workouts, workout-templates, dashboard, and the admin catalog below. Route names are prefixed `api.v1.`. Clients send `Accept-Language`; responses declare `Content-Language`. Response envelope, pagination, error shape and status-code rules are in [API Design](api-design.md). The full contract lives in [`contracts/openapi.yaml`](../contracts/openapi.yaml).

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
| Update current user (merge patch) | `PATCH` | `/api/v1/me`              | `api.v1.me.update`               | Auth                     | Auth & Profile |
| Change password     | `PUT`    | `/api/v1/me/password`                   | `api.v1.me.password`             | Auth                     | Auth & Profile |
| Delete account      | `DELETE` | `/api/v1/me`                            | `api.v1.me.destroy`              | Auth                     | Auth & Profile |
| App options         | `GET`    | `/api/v1/meta`                          | `api.v1.meta`                    | Public                   | Auth & Profile |


| Action              | Method   | Path                                    | Route Name                       | Access                   | Owning Feature |
| ------------------- | -------- | --------------------------------------- | -------------------------------- | ------------------------ | -------------- |
| List exercises      | `GET`    | `/api/v1/exercises`                     | `api.v1.exercises.index`         | Auth + verified          | Workout Logging |
| List equipment      | `GET`    | `/api/v1/admin/equipment`               | `api.v1.admin.equipment.index`   | Auth + verified + admin  | Admin          |
| Show equipment      | `GET`    | `/api/v1/admin/equipment/{equipmentId}`   | `api.v1.admin.equipment.show`    | Auth + verified + admin  | Admin          |
| Create equipment    | `POST`   | `/api/v1/admin/equipment`               | `api.v1.admin.equipment.store`   | Auth + verified + admin  | Admin          |
| Update equipment    | `PUT`    | `/api/v1/admin/equipment/{equipmentId}`   | `api.v1.admin.equipment.update`  | Auth + verified + admin  | Admin          |
| Delete equipment    | `DELETE` | `/api/v1/admin/equipment/{equipmentId}`   | `api.v1.admin.equipment.destroy` | Auth + verified + admin  | Admin          |
| List categories     | `GET`    | `/api/v1/admin/categories`              | `api.v1.admin.categories.index`  | Auth + verified + admin  | Admin          |
| Show category       | `GET`    | `/api/v1/admin/categories/{categoryId}`   | `api.v1.admin.categories.show`   | Auth + verified + admin  | Admin          |
| Create category     | `POST`   | `/api/v1/admin/categories`              | `api.v1.admin.categories.store`  | Auth + verified + admin  | Admin          |
| Update category     | `PUT`    | `/api/v1/admin/categories/{categoryId}`   | `api.v1.admin.categories.update` | Auth + verified + admin  | Admin          |
| Delete category     | `DELETE` | `/api/v1/admin/categories/{categoryId}`   | `api.v1.admin.categories.destroy`| Auth + verified + admin  | Admin          |
| List exercises      | `GET`    | `/api/v1/admin/exercises`               | `api.v1.admin.exercises.index`   | Auth + verified + admin  | Admin          |
| Show exercise       | `GET`    | `/api/v1/admin/exercises/{exerciseId}`    | `api.v1.admin.exercises.show`    | Auth + verified + admin  | Admin          |
| Create exercise     | `POST`   | `/api/v1/admin/exercises`               | `api.v1.admin.exercises.store`   | Auth + verified + admin  | Admin          |
| Update exercise     | `PUT`    | `/api/v1/admin/exercises/{exerciseId}`    | `api.v1.admin.exercises.update`  | Auth + verified + admin  | Admin          |
| Delete exercise     | `DELETE` | `/api/v1/admin/exercises/{exerciseId}`    | `api.v1.admin.exercises.destroy` | Auth + verified + admin  | Admin          |
| List templates      | `GET`    | `/api/v1/admin/workout-templates`       | `api.v1.admin.workout-templates.index`   | Auth + verified + admin | Admin |
| Show template       | `GET`    | `/api/v1/admin/workout-templates/{workoutTemplateId}` | `api.v1.admin.workout-templates.show` | Auth + verified + admin | Admin |
| Create template     | `POST`   | `/api/v1/admin/workout-templates`       | `api.v1.admin.workout-templates.store`   | Auth + verified + admin | Admin |
| Update template     | `PUT`    | `/api/v1/admin/workout-templates/{workoutTemplateId}` | `api.v1.admin.workout-templates.update` | Auth + verified + admin | Admin |
| Delete template     | `DELETE` | `/api/v1/admin/workout-templates/{workoutTemplateId}` | `api.v1.admin.workout-templates.destroy` | Auth + verified + admin | Admin |
| List programs (admin) | `GET`  | `/api/v1/admin/programs`                | `api.v1.admin.programs.index`    | Auth + verified + admin  | Admin          |
| Show program (admin)  | `GET`  | `/api/v1/admin/programs/{programId}`      | `api.v1.admin.programs.show`     | Auth + verified + admin  | Admin          |
| Create program      | `POST`   | `/api/v1/admin/programs`                | `api.v1.admin.programs.store`    | Auth + verified + admin  | Admin          |
| Update program      | `PUT`    | `/api/v1/admin/programs/{programId}`      | `api.v1.admin.programs.update`   | Auth + verified + admin  | Admin          |
| Delete program      | `DELETE` | `/api/v1/admin/programs/{programId}`      | `api.v1.admin.programs.destroy`  | Auth + verified + admin  | Admin          |

## Related

- [Product Overview](product.md)
- [Programs](features/programs.md)
- [Workout Logging](features/workout-logging.md)
- [Auth & Profile](features/auth-and-profile.md)
- [Admin Content Management](features/admin-content-management.md)

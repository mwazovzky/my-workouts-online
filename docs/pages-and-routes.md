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

Single API endpoint: `GET /api/user` with Sanctum authentication.

## Related

- [Product Overview](product.md)
- [Programs](features/programs.md)
- [Workout Logging](features/workout-logging.md)
- [Auth & Profile](features/auth-and-profile.md)

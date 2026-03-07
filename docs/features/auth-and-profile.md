# Auth & Profile

Authentication and profile management via Laravel Breeze.

## Current Behavior

1. **Register** — name, email, password. Email verification prompt follows
2. **Login / Logout** — email + password, session-based. Logout via POST
3. **Password Reset** — sends reset link, user sets new password via token form
4. **Email Verification** — signed URL, throttled 6/min, required for app pages (`verified` middleware)
5. **Profile** — update name/email (email change resets verification), change password, switch locale, or delete account (password confirmation required)

## Business Rules

- Registration and login pages are guest-only (redirect if already authenticated)
- All app pages (dashboard, programs, workouts) require `auth` + `verified` middleware
- Profile routes require `auth` only (no `verified` — allows access during re-verification)
- Password confirmation page gates sensitive actions
- Locale is stored on the user record and updated from the profile page
- Account deletion is permanent — no soft delete

## Known Limitations

- No social/OAuth login
- No two-factor authentication
- No "remember me" persistence option visible in UI
- No user avatar or profile picture
- No guest locale switcher — guests always use English
- Roles exist (`Role` model, `role_user` pivot, seeder) but are not consumed by any gate, policy, or middleware — scaffolding for future RBAC
- No admin panel or admin role enforcement

## Surface Area

- Pages: `Register`, `Login`, `Forgot Password`, `Reset Password`, `Verify Email`, `Confirm Password`, `Profile/Edit`
- Route names: `register`, `login`, `password.request`, `password.email`, `password.reset`, `password.store`, `verification.notice`, `verification.verify`, `verification.send`, `password.confirm`, `password.update`, `profile.edit`, `profile.update`, `profile.locale`, `profile.destroy`, `logout`
- Full reference: [Pages & Routes](../pages-and-routes.md)

## Related

- [Programs](programs.md) — requires authenticated + verified user
- [Workout Logging](workout-logging.md) — owner-scoped, requires authentication
- [Architecture](../architecture.md) — locale resolution and translations model

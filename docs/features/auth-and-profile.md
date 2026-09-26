# Auth & Profile

Authentication and profile management via Laravel Breeze.

## Current Behavior

1. **Register** — name, email, password. Email verification prompt follows
2. **Login / Logout** — email + password, session-based. Logout via POST
3. **Password Reset** — sends reset link, user sets new password via token form
4. **Email Verification** — signed URL, throttled 6/min, enforced for app pages and verified API routes (`verified` middleware; JSON clients get 403 `email_unverified`). Accounts created before enforcement were backfilled as verified
5. **Profile** — update name/email (email change resets verification), change password, switch locale, or delete account (password confirmation required)
6. **API tokens** — `POST /api/v1/auth/login` (email, password, `client`) issues a 15-minute JWT access token plus a 30-day rotating refresh token; `POST /api/v1/auth/refresh` rotates it; `POST /api/v1/auth/logout` revokes the current device's token family. Web clients keep the refresh token in an httpOnly cookie, native clients in secure storage
7. **API account flows** — `POST /api/v1/auth/register` creates the account, sends the verification email and returns tokens; `POST /api/v1/auth/password/forgot` and `/reset` drive password reset; `POST /api/v1/auth/email/verify` accepts the emailed link's `id`, `hash`, `expires`, `signature` and `/resend` re-sends it; `POST /api/v1/auth/reauth` re-checks the password and returns an access token with a fresh `auth_time` for step-up checks
8. **Bootstrap** — `GET /api/v1/me` returns the signed-in user (`id`, `name`, `email`, `locale`, `theme_preference`, `is_admin`, `email_verified`); `GET /api/v1/meta` is public and lists `available_locales` and `theme_preferences`
9. **Email links** — verification and reset emails link to `FRONTEND_URL` (`/verify-email/{id}/{hash}?expires&signature`, `/reset-password/{token}?email`). Verification links are signed exactly like the `verification.verify` web route, so the Inertia page and the API accept the same email

## SPA Flows (`apps/web`)

- `/register` posts to `/api/v1/auth/register`, stores the session and lands on `/verify-email`
- `/verify-email/{id}/{hash}?expires&signature` posts the link parameters to `/api/v1/auth/email/verify`, refreshes `/me` and continues to the dashboard
- `/forgot-password` and `/reset-password/{token}?email` drive the reset; a successful reset returns to `/login?reset=1`
- `/confirm-password` performs step-up through `/api/v1/auth/reauth`
- Route guards: `guest` pages redirect signed-in users to the dashboard; `verified` pages redirect unverified users to `/verify-email`; guests are sent to `/login?redirect=...`

## Business Rules

- Registration and login pages are guest-only (redirect if already authenticated)
- All app pages (dashboard, programs, workouts) require `auth` + `verified` middleware
- Profile routes require `auth` only (no `verified` — allows access during re-verification)
- Password confirmation page gates sensitive actions
- Locale is stored on the user record and updated from the profile page
- Account deletion is permanent — no soft delete
- API login is throttled per email + IP (5 attempts) like the web login and never starts a session
- Reusing a refresh token that was already rotated revokes every token in that family; changing the password revokes all other families; deleting the account revokes all
- Access tokens stay valid until they expire (max 15 min) even after logout — clients discard them
- Password reset revokes every refresh-token family; `forgot` always answers 202 so it never reveals whether an email exists
- Re-authentication requires a bearer token (session users use the web confirm-password page)

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
- API route names: `api.v1.me`, `api.v1.meta`, `api.v1.auth.register`, `api.v1.auth.login`, `api.v1.auth.refresh`, `api.v1.auth.logout`, `api.v1.auth.reauth`, `api.v1.auth.password.forgot`, `api.v1.auth.password.reset`, `api.v1.auth.email.verify`, `api.v1.auth.email.resend`, `jwks`
- Route names: `register`, `login`, `password.request`, `password.email`, `password.reset`, `password.store`, `verification.notice`, `verification.verify`, `verification.send`, `password.confirm`, `password.update`, `profile.edit`, `profile.update`, `profile.locale`, `profile.destroy`, `logout`
- Full reference: [Pages & Routes](../pages-and-routes.md)

## Related

- [Programs](programs.md) — requires authenticated + verified user
- [Workout Logging](workout-logging.md) — owner-scoped, requires authentication
- [Architecture](../architecture.md) — locale resolution and translations model

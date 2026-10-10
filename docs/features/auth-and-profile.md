# Auth & Profile

Authentication and profile management: JSON auth endpoints issued by the API, pages in the SPA.

## Current Behavior

1. **Register** — name, email, password via `POST /api/v1/auth/register`; the SPA lands on the verify-email notice
2. **Login / Logout** — email + password via `POST /api/v1/auth/login`; tokens, not sessions. Logout revokes the device's refresh-token family
3. **Password Reset** — reset link by email, new password via `POST /api/v1/auth/password/reset`
4. **Email Verification** — signed link, throttled 6/min, enforced for verified API routes (`verified` middleware; JSON clients get 403 `email_unverified`). Accounts created before enforcement were backfilled as verified
5. **Profile** — update name/email (email change resets verification), change password, switch locale and theme, or delete account (password required)
6. **API tokens** — `POST /api/v1/auth/login` (email, password, `client`) issues a 15-minute JWT access token plus a 30-day rotating refresh token; `POST /api/v1/auth/refresh` rotates it; `POST /api/v1/auth/logout` revokes the current device's token family. Web clients keep the refresh token in an httpOnly cookie, native clients in secure storage
7. **API account flows** — `POST /api/v1/auth/register` creates the account, sends the verification email and returns tokens; `POST /api/v1/auth/password/forgot` and `/reset` drive password reset; `POST /api/v1/auth/email/verify` accepts the emailed link's `id`, `hash`, `expires`, `signature` and `/resend` re-sends it; `POST /api/v1/auth/reauth` re-checks the password and returns an access token with a fresh `auth_time` for step-up checks
8. **Bootstrap** — `GET /api/v1/me` returns the signed-in user (`id`, `name`, `email`, `locale`, `theme_preference`, `is_admin`, `email_verified`); `GET /api/v1/meta` is public and lists `available_locales` and `theme_preferences`
9. **Email links** — verification and reset emails link to `FRONTEND_URL` (`/verify-email/{id}/{hash}?expires&signature`, `/reset-password/{token}?email`). Verification links are signed against the named `verification.verify` route, which only forwards to the SPA

## iOS Session (`apps/ios`)

- Logs in with `client: native` and a `device_name` (`iPhone · iOS 27.0`); both tokens are kept in the Keychain (`KeychainTokenStore`, after-first-unlock, this device only), the access token also in memory
- `AuthSession` (actor) rotates the pair through `POST /api/v1/auth/refresh` with the refresh token in the body; concurrent callers share one refresh. A 401/403 problem whose `code` starts with `refresh_token_` clears the Keychain and the app returns to the login screen with a "session expired" notice; a network failure keeps the tokens for a later retry
- `AuthMiddleware` adds the bearer to every request and, on a 401 `token_expired` (never on `unauthenticated`), refreshes once and replays the request; the auth endpoints themselves are excluded so a failed refresh cannot loop
- Launch (`AppSession.bootstrap`): no stored pair → signed out; fresh access token → `GET /me`; stale → refresh, then `/me`. The state (`signedOut`, `unverified`, `ready`) drives the root view like the SPA's route guards; an unverified user only sees the verify gate
- Logout posts `/auth/logout` with the current access token (best effort) and clears the Keychain even if the call fails

## iOS Flows (`apps/ios`)

- **Welcome** → Log in / Register. **Log in** posts `/auth/login`; a wrong password (401 `invalid_credentials`) shows the API `detail` under the form, and a lockout (429 `too_many_attempts`) shows "Too many attempts. Try again in N seconds." from `Retry-After`. "Remember me" stores only the email in `UserDefaults`
- **Register** posts `/auth/register`; the 422 problem's `errors[]` are mapped by pointer (`/name`, `/email`, `/password`, `/password_confirmation`) to their fields; success lands on the verify gate
- **Forgot password** posts `/auth/password/forgot` and always shows the neutral "We have emailed your password reset link."; the emailed link opens the web app until story 6 adds Universal Links
- **Verify email gate** (shown while `email_verified` is false): "I have verified my email" re-fetches `/me` and continues when verified; "Resend" posts `/auth/email/resend` (always 202 → sent notice; 429 → throttle message); Log out
- A 429 on any form shows a throttle banner, a network failure an offline banner; everything else shows the server's message
- **Emailed links** open the app on a phone that has it (Universal Links on the production domain; the AASA file is served by nginx, see Deployment) and keep opening the web app everywhere else. A verification link verifies immediately when the linked user is signed in; when signed out it is kept and completes after login ("Sign in to finish verifying your email."); for another signed-in account the app explains and offers to log out. A reset link opens the reset form as a sheet in any state; success signs out locally, since the server revoked every session, and the welcome screen says "Your password has been reset.". The `myworkouts://` scheme mirrors both paths for simulator testing (`xcrun simctl openurl`)
- **Profile tab**: name and email (`PATCH /profile`; a new email returns the user to the verify gate, with resend offered while unverified), change password (`PUT /profile/password`), theme (`PATCH /profile/theme`, applied at once and cached for the next launch), Log Out, Delete Account (password confirmation, `DELETE /profile`, then signed out locally), and the app version. **Language** follows the iOS per-app language: the row opens the app's page in Settings instead of an in-app picker, and on launch and on becoming active the app syncs the resolved language to `PATCH /profile/locale` when it differs from `user.locale`, so the web shows the same language

## SPA Flows (`apps/web`)

- `/register` posts to `/api/v1/auth/register`, stores the session and lands on `/verify-email`
- `/verify-email/{id}/{hash}?expires&signature` posts the link parameters to `/api/v1/auth/email/verify`, refreshes `/me` and continues to the dashboard
- `/forgot-password` and `/reset-password/{token}?email` drive the reset; a successful reset returns to `/login?reset=1`
- `/confirm-password` performs step-up through `/api/v1/auth/reauth`
- Route guards: `guest` pages redirect signed-in users to the dashboard; `verified` pages redirect unverified users to `/verify-email`; guests are sent to `/login?redirect=...`

## Business Rules

- Registration and login pages are guest-only (the SPA redirects signed-in users to the dashboard)
- App API routes (dashboard, programs, workouts) require `auth:jwt` + `verified`
- Profile routes require `auth:jwt` only (no `verified` — allows access during re-verification)
- `/confirm-password` performs step-up re-authentication through `POST /api/v1/auth/reauth`
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
- Roles are granted from the command line only (`php artisan user:grant-admin {email}`, for a verified user); there is no UI to grant or revoke a role

## Surface Area

- SPA pages: `auth/Register`, `auth/Login`, `auth/ForgotPassword`, `auth/ResetPassword`, `auth/VerifyEmail`, `auth/VerifyEmailLink`, `auth/ConfirmPassword`, `Profile/Edit`
- API route names: `api.v1.me`, `api.v1.meta`, `api.v1.auth.register`, `api.v1.auth.login`, `api.v1.auth.refresh`, `api.v1.auth.logout`, `api.v1.auth.reauth`, `api.v1.auth.password.forgot`, `api.v1.auth.password.reset`, `api.v1.auth.email.verify`, `api.v1.auth.email.resend`, `jwks`
- Full reference: [Pages & Routes](../pages-and-routes.md)

## Related

- [Programs](programs.md) — requires authenticated + verified user
- [Workout Logging](workout-logging.md) — owner-scoped, requires authentication
- [Architecture](../architecture.md) — locale resolution and translations model

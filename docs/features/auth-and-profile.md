# Auth & Profile

Standard user authentication and profile management. Built on Laravel Breeze.

## Current Behavior

**Registration** — User registers with name, email, password. Email verification prompt shown after registration.

**Login / Logout** — Email + password login. Session-based authentication. Logout via POST.

**Password Reset** — "Forgot password" sends reset link via email. User sets new password via token-authenticated form.

**Email Verification** — Signed URL sent via email. Throttled to 6 attempts per minute. Verified status required for all app pages (`verified` middleware).

**Profile Management** — On **Profile/Edit**, user can:
- Update name and email (email change resets verification)
- Change password
- Delete account (with password confirmation)

## Business Rules

- Registration and login pages are guest-only (redirect if already authenticated)
- All app pages (dashboard, programs, workouts) require `auth` + `verified` middleware
- Profile routes require `auth` only (no `verified` — allows access during re-verification)
- Password confirmation page gates sensitive actions
- Account deletion is permanent — no soft delete

## Known Limitations

- No social/OAuth login
- No two-factor authentication
- No "remember me" persistence option visible in UI
- No user avatar or profile picture
- Roles exist (`Role` model, `role_user` pivot, seeder) but are not consumed by any gate, policy, or middleware — scaffolding for future RBAC
- No admin panel or admin role enforcement

## Pages & Routes

| Page | Route | Name | Access |
|---|---|---|---|
| Register | `GET /register` | `register` | Guest |
| Login | `GET /login` | `login` | Guest |
| Forgot Password | `GET /forgot-password` | `password.request` | Guest |
| Reset Password | `GET /reset-password/{token}` | `password.reset` | Guest |
| Verify Email | `GET /verify-email` | `verification.notice` | Auth |
| Confirm Password | `GET /confirm-password` | `password.confirm` | Auth |
| Profile Edit | `GET /profile` | `profile.edit` | Auth |

| Action | Method | Path | Name |
|---|---|---|---|
| Register | POST | `/register` | — |
| Login | POST | `/login` | — |
| Send reset link | POST | `/forgot-password` | `password.email` |
| Reset password | POST | `/reset-password` | `password.store` |
| Send verification | POST | `/email/verification-notification` | `verification.send` |
| Confirm password | POST | `/confirm-password` | — |
| Update password | PUT | `/password` | `password.update` |
| Update profile | PATCH | `/profile` | `profile.update` |
| Delete account | DELETE | `/profile` | `profile.destroy` |
| Logout | POST | `/logout` | `logout` |

## Related

- [Programs](programs.md) — requires authenticated + verified user
- [Workout Logging](workout-logging.md) — owner-scoped, requires authentication

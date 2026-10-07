# User Story: Auth and Ownership Status Codes

Return 401 for bad credentials, 429 for a login lockout, 404 for every action on a resource the caller does not own, and a single 202 from resend-verification.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: Workout Logging (ownership responses on save, complete, repeat, delete)
- Creates New Feature: No

## Problem Statement

`LoginRequest::authenticateUser()` reports a wrong password as a 422 validation error on `email`, and a lockout as another 422, so a client cannot tell "fix your input" from "wrong password" from "wait". The conventional answers are `401` and `429` with `Retry-After`. `WorkoutController@show` returns 404 for a workout the caller does not own, but `WorkoutPolicy` makes `save`, `complete`, `repeat` and `delete` return 403 for the same workout, which is inconsistent and reveals that the id exists. `resendVerification` returns 202 or 204 depending on state, which is an unusual pair for one operation.

## User-Visible Outcome

Login shows "wrong email or password" as before, and a locked-out user now sees how long to wait. Nothing else changes visibly.

## Scope

- Contract: `login` gains `401` (`code: invalid_credentials`, added to `ErrorCode`) and keeps `429` (`code: too_many_attempts`, added); `422` on `login` is only for malformed input. `saveWorkout`, `completeWorkout`, `repeatWorkout`, `deleteWorkout` drop `403` and keep `404`. `resendVerification` returns `202` only.
- PHP: `LoginRequest` throws an `ApiException(401, 'invalid_credentials')` instead of a `ValidationException`, and `ApiException(429, 'too_many_attempts')` with `Retry-After` on lockout; `WorkoutPolicy` ownership checks move to the controller (or a `ResolvesOwnedWorkout` helper) that aborts 404, leaving the policy for status checks (`save`/`complete` on a completed workout, `repeat` on an in-progress one → 403 as today); `EmailVerificationController@resend` returns 202 always.
- Web: `apps/web/src/pages/auth/Login.vue` maps 401 to the existing message and 429 to a "try again in N seconds" message from `Retry-After`; `useApiForm` no longer expects login failures as field errors.
- iOS: `AuthViewModels.swift` and `FormErrors.swift` handle `.unauthorized` and `.tooManyRequests` on `login`; `AuthRepository.swift` maps the new cases.
- `npm run api:types` regenerated.

## Non-Goals

- changing the lockout window or attempt count
- 404 for admin resources (admins see everything)
- `WWW-Authenticate` headers (bearer challenge is out of scope for a form login)

## Acceptance Criteria

- Redocly lint green; Spectral green with the status-code rules enabled
- `scripts/contract-test.sh` green on the dev stack
- PHP suite green; web `npm test` and `npm run build` green; iOS `swift test` in `WorkoutsCore` and `xcodebuild test` green
- `npm run api:types` regenerated
- wrong password → 401 `invalid_credentials`; sixth attempt in a minute → 429 with `Retry-After`
- another user's workout → 404 on GET, PATCH save, POST complete, POST repeat, DELETE
- own completed workout → PATCH save still 403
- `POST /auth/email/resend` → 202 whether or not the email is verified

## Initial Tasks

- [ ] contract responses and `ErrorCode` additions
- [ ] `LoginRequest` exceptions; `Retry-After` from the rate limiter
- [ ] ownership → 404 in `WorkoutController`; policy keeps status rules
- [ ] `resend` → 202
- [ ] web login page and form composable
- [ ] iOS auth view models, repository, tests
- [ ] regenerate web types; docs

## Test Cases

- `tests/Feature/Api/Auth/LoginTest.php`: 401 body, 429 body and header, 422 for a malformed email
- `tests/Feature/WorkoutAuthorizationTest.php` and `tests/Feature/Workout/*Test.php`: non-owner 404 for every action; status 403 cases unchanged
- `tests/Feature/Api/Auth/EmailVerificationTest.php`: resend 202 in both states
- `apps/web/src/pages/auth/Login.test.js`: 401 and 429 rendering
- iOS `AuthViewModelTests.swift`: `invalidCredentials` and `lockedOut(retryAfter:)` states

## Docs To Update

- `docs/features/auth-and-profile.md` (login failure responses, lockout)
- `docs/features/workout-logging.md` (ownership: 404 everywhere)
- `docs/pages-and-routes.md` (login page states)

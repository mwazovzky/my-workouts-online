# User Story: PHP Parity Fixes

Bring the PHP API up to the behaviour the Go API chose where PHP has a bug or a gap, so that switching `API_BACKEND` changes nothing a user or a client can notice beyond the documented, deliberate differences.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: Auth & Profile, Admin Content Management, Programs
- Creates New Feature: No

## Problem Statement

Building the Go API meant comparing every operation with PHP. The departures are recorded in the Go ADRs (0006, 0007, 0008, 0010, 0012, 0013). Most are deliberate and harmless: storage design, error wording, validation order. Others show PHP doing something wrong, and Go does it right:

- `exists:exercises,id` ignores soft deletes, so a retired exercise, hidden from every picker, can be added to a workout (`ReplaceWorkoutActivitiesRequest`) or a template (`Admin/WorkoutTemplate*Request`) by id.
- A set order repeated within one activity reaches the `(activity_id, order)` unique key and answers 500. The admin template requests also lack `distinct` on activity orders, so a repeated activity order is a 500 there too.
- Login for an unknown email skips the password hash (`LoginRequest::authenticateUser`), so it answers measurably faster than a wrong password and reveals which emails have accounts.
- Changing the email (`MeController::update`) clears `email_verified_at` but sends no verification link. The user is locked out of every verified route until they find "resend".
- `RateLimiter::for('api')` is defined in `AppServiceProvider`, but `throttle:api` is attached to no route, so `API_RATE_LIMIT_PER_MINUTE` does nothing.
- A wrong reauth password is reported as `invalid_value` (a hand-thrown `ValidationException` falls to `FieldErrorCode`'s default). The contract's code for it is `mismatch`. A password shorter than the minimum is `invalid_format` (the `Password` rule maps as a whole), where `too_short` is the precise code.
- An existing activity sent with a different `exercise_id` is accepted, and the change is silently dropped.
- A set id that belongs to another activity is reported on `/activities/i/sets` with `invalid_value`, not on the set.
- `GET /programs` has no `ORDER BY`, so its order is whatever MySQL returns.
- `lang/ru.json` lacks "Record not found.", "Server Error", "Too Many Attempts.", "This action is unauthorized.", the "(and :count more error(s))" suffix and "An English name is required.", so Russian clients get English for these.

Each of these is fixed in Go (ADR 0010 lists "the PHP API can adopt the retired-exercise and set-order checks" as its follow-up). Until PHP matches, the two backends answer differently on these payloads, and PHP keeps the bugs in production.

## User-Visible Outcome

- A user who changes their email gets a verification link at the new address.
- Russian-speaking users see Russian messages for not-found, forbidden, rate-limited and server errors.
- Nothing else changes for users of the web or iOS apps: their pickers already hide retired exercises, and their editors never send repeated orders or change an existing activity's exercise. The fixes close payloads only a hand-made request can send.

## Scope

Must:

1. **Retired exercises on write.** In the workout save and in both admin template requests, an exercise must exist and not be retired. The one exception: an activity that already has a retired exercise may keep it. A violation is 422 `not_found` at `/activities/i/exercise_id`. Implement this as a rule that knows the existing activity's exercise (or as a check in `WorkoutService` / `WorkoutTemplateService`), not with a bare `exists`.
2. **Repeated orders are 422.**
   - A set order repeated within one activity is 422 `not_unique` at `/activities/i/sets/j/order`, in the workout save and in the admin templates. Laravel's `distinct` on `activities.*.sets.*.order` compares across all activities, so this needs a per-activity check (an `after()` hook or a custom rule).
   - The admin template requests add `distinct` to `activities.*.order`.
3. **Login timing.** When no user matches the email, check the password against a fixed dummy bcrypt hash before throwing `invalid_credentials`, so both failures cost one hash.
4. **Verification on email change.** When `MeController::update` changes the email, send the verification notification to the new address after saving. A mail failure is logged and does not fail the request, as in register.
5. **Global API rate limit.** Attach `throttle:api` to the `/api/v1` route group, so `API_RATE_LIMIT_PER_MINUTE` applies per client IP with `X-RateLimit-Limit` and `X-RateLimit-Remaining`, as in Go. The limiter keys on `$request->ip()`. Under FastCGI it is the client's address as nginx sees it, and no `TrustProxies` is configured. Confirm that production has no other proxy in front of nginx; if it has, trust that hop only.
6. **Precise field codes.**
   - Reauth with a wrong password: 422 `mismatch` on `/password`.
   - Register (and every other `Password::defaults()` use) with a password that is too short: `too_short`.
   - Other failures of the `Password` rule stay `invalid_format`.

Should:

7. **An existing activity may change its exercise.** The workout save stores a changed `exercise_id`, subject to rule 1.
8. **Set id pointer.** A set id that is not its activity's is 422 `not_found` at `/activities/i/sets/j/id`.
9. **Program list order.** `GET /programs` is ordered by id.
10. **Russian messages.** Add the six strings above to `lang/ru.json`, using the translations already in `services/api-go/internal/kernel/i18n/locales/ru.json` so both backends say the same thing.

## Non-Goals

- Changing the Go API. Where Go is weaker (the 60-second throttle on a new reset token; a fully failed forward not retried on webhook redelivery; a field omitted from a sent admin locale cleared rather than kept) is a separate Go follow-up.
- The deliberate, harmless differences recorded in the ADRs: schema shape, the signed-link scheme, the reset-token storage, the malformed-JSON body error, validation error order, 405 wording, the `X-Requested-With` status, `PATCH /me {}`, the catalog-sync rewrite rule, `iat`/`exp` precision.
- Localized email bodies (Laravel's default verification and reset mails stay English). This is worth a story of its own if wanted.
- Per-operation throttle buckets in PHP. PHP's throttled routes share Laravel's default key per user or IP; Go keys each operation separately. Tolerable; revisit only if someone hits it.
- Any change to `contracts/openapi.yaml`, the web SPA or the iOS app.

## Acceptance Criteria

- `PUT /workouts/{workoutId}/activities` and the admin template create and update:
  - answer 422 `not_found` at `/activities/i/exercise_id` for a retired exercise on a new activity, or one swapped onto an existing activity;
  - accept a retired exercise an existing activity already has.
- A set order repeated within one activity, and (in admin templates) a repeated activity order, answer 422 `not_unique` with that pointer. No payload produces a 500.
- A test proves that a login for an unknown email performs one bcrypt check (`Hash::check` is called, or a spy hasher records it), and still answers 401 `invalid_credentials`.
- `PATCH /me` with a new email:
  - sends `VerifyEmail` to the new address (`Notification::fake()`);
  - an unchanged email sends nothing;
  - a mail exception still returns 200.
- With `API_RATE_LIMIT_PER_MINUTE=2`, the third request to an `/api/v1` route within a minute answers 429 `too_many_attempts` with `Retry-After`. The existing per-route throttles still apply on top.
- Reauth with a wrong password answers 422 `mismatch` on `/password`; register with a 3-character password answers `too_short`.
- A changed `exercise_id` on an existing activity is stored and returned.
- A foreign set id answers 422 `not_found` at `/activities/i/sets/j/id`.
- `GET /programs` is ordered by id.
- With `Accept-Language: ru`, the 404, 403, 429 and 500 `detail`, and the error-count suffix, are Russian and equal to Go's.
- The PHP suite, Pint, the PHP Schemathesis run (`contract.yml`) and the web tests are green.
- After the fixes, the scripted comparison flows used in Go stories 8 and 10 give the same responses on both backends for every case listed under Must and Should.

## Initial Tasks

- [ ] Rule or service check for retired exercises (workout save, admin template create and update)
- [ ] Per-activity set-order uniqueness; `distinct` on admin activity orders
- [ ] Dummy-hash check in `LoginRequest::authenticateUser`
- [ ] Verification notification on email change in `MeController::update`
- [ ] `throttle:api` on the `/api/v1` group; confirm the client IP in production
- [ ] Reauth `mismatch`; `too_short` for the password minimum in `FieldErrorCode` / `ApiErrorResponse`
- [ ] Store a changed `exercise_id`; foreign set id pointer
- [ ] `orderBy('id')` in `ProgramController::index`
- [ ] Six strings in `lang/ru.json`
- [ ] Update ADR follow-ups and docs (below)

## Test Cases

PHPUnit feature tests:

- `tests/Feature/Workout/` (save):
  - retired exercise added, swapped onto an existing activity, or kept;
  - repeated set order within one activity, and the same order in two activities (allowed);
  - changed exercise stored;
  - foreign set id pointer.
- `tests/Feature/Admin/` (workout templates): retired exercise added or kept; repeated activity order; repeated set order.
- `tests/Feature/Api/Auth/`:
  - login for an unknown email checks a hash;
  - reauth `mismatch`;
  - register `too_short`.
- `tests/Feature/Api/MeApiTest.php`: the email change notifies the new address, an unchanged email sends nothing, a mail failure still answers 200.
- API rate limit: the third request with a limit of 2 is a 429 with `Retry-After`.
- `tests/Feature/Api/ProgramApiTest.php`: the list is ordered by id.
- `tests/Feature/Api/ErrorEnvelopeTest.php`: a 404 with `Accept-Language: ru` has the Russian `detail`.

Unit: `FieldErrorCode::fromRule` for the `Password` rule's minimum.

## Docs To Update

- `docs/features/workout-logging.md`: drop the "differs only on payloads PHP mishandles" paragraph; state the rules for both backends.
- `docs/features/admin-content-management.md`: the same for templates.
- `docs/features/auth-and-profile.md`: the email change sends a new verification link; the global API rate limit.
- `services/api-go/docs/adr/0006`, `0007`, `0008`, `0010`, `0012`: mark the departures this story removes as aligned (keep the tables, add "aligned in the PHP parity story").
- `services/api-go/README.md`: the workout and admin departure lists.

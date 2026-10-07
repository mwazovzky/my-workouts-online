# User Story: Enrollment as a Resource

Replace `POST /programs/{program}/enroll` with an idempotent `PUT` / `DELETE` on `/programs/{programId}/enrollment`, backed by a unique constraint.

## Feature Mapping

- Primary Feature: Programs
- Secondary Features: none
- Creates New Feature: No

## Problem Statement

Enrollment is an action endpoint with no inverse: a user can enroll but never unenroll, and the only reason the second call is harmless is that the controller uses `syncWithoutDetaching`. `program_user` has no unique constraint, so a race can insert two rows and the dashboard would schedule the program twice. Modelling the enrollment as a resource gives `PUT` (idempotent by definition) and `DELETE` for free, and lets the dashboard count rows safely.

## User-Visible Outcome

Enrolling works as before. An "Unenroll" control may appear on the program page; if it is left out of this story, nothing visible changes.

## Scope

- Contract: `PUT /programs/{programId}/enrollment` (operationId `enrollProgram`, 204, idempotent) and `DELETE /programs/{programId}/enrollment` (operationId `unenrollProgram`, 204, idempotent — 204 even when not enrolled); remove `POST …/enroll`; `Program.is_enrolled` unchanged.
- PHP: routes; `ProgramController@enroll` → `PUT` handler with `syncWithoutDetaching`, `@unenroll` with `detach`; migration adding `unique(user_id, program_id)` to `program_user` after deleting duplicate rows (keep the lowest id); logs `program.unenrolled`.
- Web: `apps/web/src/composables/useEnrollment.js` calls `PUT`; optional `unenroll()` and an Unenroll button on `ProgramShow.vue` with a confirm.
- iOS: `ProgramRepository.swift` `enroll(programId:)` calls `enrollProgram` (same operationId, new path); optional `unenroll`.
- `npm run api:types` regenerated.

## Non-Goals

- enrollment history or timestamps in the API
- unenroll side effects on existing workouts (they are the user's own records and stay)
- an unenroll flow on iOS beyond the repository method

## Acceptance Criteria

- Redocly lint green; Spectral green
- `scripts/contract-test.sh` green on the dev stack
- PHP suite green; web `npm test` and `npm run build` green; iOS `swift test` in `WorkoutsCore` and `xcodebuild test` green
- `npm run api:types` regenerated
- `PUT …/enrollment` twice → 204 both times and one `program_user` row
- `DELETE …/enrollment` → 204; a second `DELETE` → 204; `GET /programs` shows `is_enrolled: false`
- `POST …/enroll` → 404
- the migration fails loudly if duplicate rows cannot be reduced, and succeeds on a database that already has duplicates in the test

## Initial Tasks

- [ ] contract paths and operationIds
- [ ] routes, controller, migration with de-dup
- [ ] web composable and page
- [ ] iOS repository
- [ ] regenerate web types; docs

## Test Cases

- `tests/Feature/Program/ProgramEnrollTest.php`: PUT idempotency, DELETE idempotency, unique constraint under a duplicate insert, dashboard schedule after unenroll
- `tests/Feature/Migrations/`: de-dup migration on seeded duplicates
- `apps/web/src/composables/useEnrollment.test.js`: PUT and DELETE calls, `is_enrolled` toggling
- iOS `ProgramViewModelTests.swift`: enroll through the new path

## Docs To Update

- `docs/features/programs.md` (enrollment resource, unenroll)
- `docs/pages-and-routes.md` (program page: unenroll control if added)
- `docs/architecture.md` (data model: `program_user` unique constraint)

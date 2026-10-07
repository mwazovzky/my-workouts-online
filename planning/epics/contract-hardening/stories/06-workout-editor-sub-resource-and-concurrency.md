# User Story: Workout Editor Sub-Resource and Concurrency

Model the editor write as `PUT /workouts/{workoutId}/activities` and protect it with `ETag` / `If-Match` so a stale editor can no longer overwrite a newer save.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: none
- Creates New Feature: No

## Problem Statement

`PATCH /workouts/{workout}/save` is a verb in the path, and its semantics are not PATCH: the body is the complete list of activities and sets, and anything missing is deleted. That is a `PUT` of the `activities` sub-resource. There is also no concurrency control: two tabs, or the iOS app restoring a draft after the web saved, silently last-write-wins. The iOS editor already guards its draft by comparing `baseUpdatedAt` with the server's `updated_at` (`WorkoutDraft.swift`, `WorkoutEditorModel.swift:159`), which is a hand-rolled ETag.

## User-Visible Outcome

Saving a workout that was changed elsewhere since it was opened shows "this workout changed, reload to continue" instead of overwriting. Everything else is unchanged.

## Scope

- Contract: `PUT /workouts/{workoutId}/activities` (operationId `replaceWorkoutActivities`, body `WorkoutActivitiesWrite` = today's `WorkoutSaveRequest`, 200 `WorkoutResponse`); remove `PATCH …/save`; `ETag` response header on every `WorkoutResponse` (`W/"<id>-<updated_at unix>"`); `If-Match` header parameter required on the PUT; `412` (`code: precondition_failed`) and `428` (`code: precondition_required`) Problem responses; both codes added to `ErrorCode`.
- PHP: route and `WorkoutController@save` → `replaceActivities`; `WorkoutResource` or a response middleware sets `ETag`; the controller compares `If-Match` (weak comparison) before calling `WorkoutService::save` and aborts 412/428 via `ApiException`; `complete` and `repeat` are unchanged (no precondition).
- Web: `apps/web/src/pages/WorkoutEdit.vue` keeps the `ETag` from the load and sends `If-Match` on save; on 412 shows the reload prompt; `useApi` exposes response headers.
- iOS: `WorkoutRepository.swift` returns the ETag with the workout; `WorkoutDraft.baseUpdatedAt` → `baseETag`; `WorkoutEditorModel` sends `If-Match` and maps `.preconditionFailed` to a reload state; `DraftStore` unchanged in storage shape except the field name.
- `scripts/contract-test.sh`: Schemathesis sends `If-Match` from the `WorkoutResponse` example or the operation is given an explicit example header value.
- `npm run api:types` regenerated.

## Non-Goals

- `If-Match` on complete, repeat or delete
- ETags on list endpoints or admin resources
- merging concurrent edits

## Acceptance Criteria

- Redocly lint green; Spectral green
- `scripts/contract-test.sh` green on the dev stack
- PHP suite green; web `npm test` and `npm run build` green; iOS `swift test` in `WorkoutsCore` and `xcodebuild test` green
- `npm run api:types` regenerated
- `GET /workouts/{id}` returns an `ETag`; `PUT …/activities` with that value succeeds and the response carries a new `ETag`
- the same PUT with the old value → 412 `precondition_failed`; without `If-Match` → 428 `precondition_required`
- `PATCH …/save` → 404
- iOS: a draft taken before a web save is reported as stale and not applied

## Initial Tasks

- [ ] contract path, headers, problems, `ErrorCode`
- [ ] route, controller precondition, `ETag` emission
- [ ] web editor `If-Match` and 412 handling; `useApi` headers
- [ ] iOS repository, draft, editor model, tests
- [ ] contract-test header example
- [ ] regenerate web types; docs

## Test Cases

- `tests/Feature/Workout/WorkoutSaveTest.php` → `WorkoutReplaceActivitiesTest.php`: 200 with matching `If-Match`, 412 stale, 428 missing, weak-tag comparison, `ETag` changes after save
- `tests/Feature/Workout/WorkoutShowTest.php`: `ETag` present
- `apps/web/src/pages/WorkoutEdit.test.js`: header sent, 412 prompt
- iOS `WorkoutEditorModelTests.swift`, `WorkoutEditorActivitiesTests.swift`: stale draft, `If-Match` on save, precondition-failed state

## Docs To Update

- `docs/features/workout-logging.md` (editor endpoint, concurrency rule, 412 flow)
- `docs/architecture.md` (HTTP semantics: sub-resources, ETags)
- `docs/pages-and-routes.md` (editor page states)

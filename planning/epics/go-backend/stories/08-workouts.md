# User Story: Workouts

Port the training module: a user can start a workout from a template, replace its activities and sets, complete it, repeat it, delete it and page through history against the Go service, with the same invariants the PHP service enforces.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: Programs (template snapshot read through `catalogapi`)
- Creates New Feature: No

## Problem Statement

The workout editor is the product's core value and carries the most behaviour per endpoint: full-replacement sync of a nested aggregate, ownership checks on every nested id, two cross-field invariants (`CompletedSetRequiresEffort`, `HeartRateZoneWithinRange`), an `order` unique constraint that the PHP service dodges with negative temporary values, a name snapshot localized at creation, and optimistic concurrency through `ETag`/`If-Match` since the contract-hardening epic. Stories 1–7 built the kernel, identity and the read side of the catalog; nothing yet writes to `workouts`, `workout_activities` or `workout_sets`.

## User-Visible Outcome

None. The web SPA and iOS app behave identically; the Go service answers the same contract for every workout operation.

## Scope

- `internal/training/domain`: `Workout` (`ID`, `UserID`, `TemplateID *int64`, `Name`, `Status`, `UpdatedAt`, `Activities`), `Activity` (`ID`, `ExerciseID`, `Order`, `Sets`), `Set` (`ID`, `Order`, `EffortValue`, `DifficultyValue *decimal`, `IsCompleted`), `Status` enum (`in_progress`, `completed`). Invariants on the types: `Set.Complete()` returns `ErrCompletedSetRequiresEffort` when `EffortValue == 0`; `Workout.Complete()` and `Workout.ReplaceActivities()` return `ErrNotInProgress` when the status is `completed`; `Workout.Repeat()` returns a new in-progress copy with `TemplateID = nil` and every set uncompleted.
- `internal/training/ports`: `WorkoutRepo` (`FindOwned(ctx, userID, id)`, `ListOwned(ctx, userID, page)`, `Create`, `ReplaceActivities`, `UpdateStatus`, `Delete`), `ExerciseLookup` and `TemplateSnapshotReader` satisfied by `catalogapi`.
- `internal/training/app` use cases:
  - `ListWorkouts`: owner-scoped, `updated_at desc, id desc`, `page` and `per_page` (default 20, max 100), `activities_count`, embedded `workout_template` omitted when the template was deleted; absolute `links.first/last/prev/next` built from the request URL by `kernel/pagination`.
  - `CreateWorkout`: reads `catalogapi.TemplateSnapshot(ctx, templateID, locale)`; copies activities and sets (`order`, `effort_value`, `difficulty_value`, `is_completed=false`); `name` is the template name in the request locale, stored as-is; unknown template → 422 `not_found` at `/workout_template_id`; 201 with `ETag`.
  - `GetWorkout`: 404 `not_found` for a workout the caller does not own, exactly as for a missing one.
  - `ReplaceActivities` (`PUT /workouts/{workoutId}/activities`): requires `If-Match` (428 `precondition_required` when absent, 412 `precondition_failed` on mismatch); 409 `workout_completed` when not in progress; every `activities[i].id` must belong to the workout and every `sets[j].id` to its activity → 422 `not_found` with pointers `/activities/0/id`, `/activities/0/sets/1/id`; activities absent from the payload are deleted with their sets; kept activities and sets first receive `order = -id` inside the transaction so the `(workout_id, "order")` and `(activity_id, "order")` uniques never trip mid-sync; existing activities update `order` and `exercise_id`; sets are upserted by id and created when `id` is null; `CompletedSetRequiresEffort` → 422 `invalid_value` at `/activities/i/sets/j/is_completed`; `HeartRateZoneWithinRange` (via `catalogapi.ExerciseLookup`, which resolves retired exercises) → 422 `out_of_range` at `/activities/i/sets/j/difficulty_value`; a retired exercise on a new activity → 422 `not_found` at `/activities/i/exercise_id` (the PHP `exists` rule accepted it; recorded as an intentional difference in ADR 0010).
  - `CompleteWorkout`: `Workout.Complete()`, 409 when already completed, returns the workout with a new `ETag`.
  - `RepeatWorkout`: 409 unless `completed`; 201 with the copy.
  - `DeleteWorkout`: 204; activities and sets go through `ON DELETE CASCADE`.
- `internal/training/adapters/postgres`: sqlc queries in `sql/training/*.sql`; the replace runs in one `txn.Transactor` call; `ETag` is `W/"<id>-<updated_at unix nanos>"` computed in the adapter so the HTTP layer only echoes it.
- `internal/training/adapters/http`: the seven operations of `gen.StrictServerInterface`; `ETag` header on every `WorkoutResponse`; the nested `exercise` snapshot on each activity (`id`, `name`, `equipment_name`, `effort_type`, `difficulty_unit`, `category_names`) built from `catalogapi.ExerciseLookup` in the request locale.
- `internal/training/trainingapi`: `RecentWorkouts(ctx, userID, n)`, `InProgress(ctx, userID)`, `Counts(ctx, userID, since)` for story 9.
- Append the seven operation ids to `services/api-go/contract-scope.txt`.

## Non-Goals

- dashboard aggregation (story 9)
- admin template management (story 10)
- `Idempotency-Key` on `createWorkout`/`repeatWorkout` (deferred, see the epic Non-Goals)
- any change to `apps/web` or `apps/ios`

## Acceptance Criteria

- `scripts/contract-test.sh http://localhost:8081` with `CONTRACT_INCLUDE` covering the seven operations is green
- every mutating operation has an owner test and an other-user test; the other user receives 404 with the Problem Details body and no side effect
- a replace payload that drops one activity and reorders the rest leaves exactly the payload's activities, with `order` values 1..n and no unique-constraint error, verified against Postgres
- a `PUT` with a stale `If-Match` returns 412 and changes nothing; without `If-Match` returns 428
- marking a set completed with `effort_value: 0` returns 422 with pointer `/activities/0/sets/0/is_completed` and code `invalid_value`
- a heart-rate-zone exercise rejects `difficulty_value: 6` with pointer `/activities/0/sets/0/difficulty_value` and code `out_of_range`
- `repeatWorkout` on an in-progress workout returns 409; on a completed one returns 201 with `workout_template` absent and every `is_completed` false
- `listWorkouts?page=2&per_page=5` returns `meta.current_page=2`, `meta.per_page=5`, correct `from`/`to`/`total`, and `links.prev` is an absolute URL
- `make check` green with `-race`

## Initial Tasks

- [ ] domain types and invariant tests in `internal/training/domain`
- [ ] `sql/training/*.sql` and `sqlc generate`; `WorkoutRepo` with the replace transaction and negative temporary orders
- [ ] use cases with fakes for `ExerciseLookup` and `TemplateSnapshotReader`
- [ ] HTTP adapter, `ETag` handling, nested `exercise` snapshot, pagination links
- [ ] `trainingapi` package
- [ ] HTTP tests mirroring `tests/Feature/Workout/*` and `WorkoutAuthorizationTest`, golden files per response
- [ ] ADR 0010 (retired exercises rejected on write)
- [ ] `contract-scope.txt`, docs

## Test Cases

- unit: `Set.Complete()` zero effort; `Workout.Complete()`/`ReplaceActivities()` on completed; `Repeat()` copies sets uncompleted and drops the template
- repo (testcontainers): replace with deletions, reorders and new sets in one call; concurrent replace on the same workout serialises without a unique violation
- HTTP: each of `tests/Feature/Workout/{Store,Show,Save,Complete,Repeat,Delete}Test.php` and `tests/Feature/WorkoutAuthorizationTest.php` ported case by case, plus `If-Match` 412/428 and the `per_page` bounds
- contract: Schemathesis scoped to the seven operations, all checks

## Docs To Update

- `docs/features/workout-logging.md` (Go implementation notes: `ETag` scheme, retired-exercise rejection)
- `docs/architecture.md` (training module, `trainingapi` surface)
- `services/api-go/docs/adr/0010-retired-exercises-rejected-on-write.md`

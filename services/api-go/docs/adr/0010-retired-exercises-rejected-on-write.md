# 0010. Workout Writes: Retired Exercises Rejected, and Other Departures from PHP

- Status: accepted
- Date: 2026-10-12
- Story: [Go epic story 08](../../../../planning/epics/go-backend/stories/08-workouts.md)

## Context

The workout editor's save (`PUT /workouts/{workoutId}/activities`) replaces a workout's activities and sets in one request: rows missing from the payload are deleted, rows with an id are updated, rows without one are created, under optimistic concurrency (`ETag`/`If-Match`). PHP splits its checks between the form request (`ReplaceWorkoutActivitiesRequest`, run before the controller) and the service (`WorkoutService::save`). Porting it as is would copy a few gaps:

- the `exists:exercises,id` rule does not see soft deletes, so a retired exercise, hidden from the picker, can still be added to a workout by id;
- an existing activity's `exercise_id` is accepted and then silently ignored (only `order` is updated);
- a set id that is not its activity's is reported on `/sets` with `invalid_value`, not on the set;
- a set order repeated within an activity reaches the `(activity_id, order)` unique key as a 500.

## Decision

The Go use case (`internal/training/app/replace.go`) checks, in this order: someone else's workout is a 404; a completed one a 403; a missing `If-Match` a 428; then, under the workout's `SELECT … FOR UPDATE`, a stale `If-Match` a 412 (strong comparison: `*` and tag lists match, weak tags never), and only then the payload's business rules, all reported at once with a pointer each:

- an activity id that is not the workout's, or a set id that is not its activity's, is `not_found` at `/activities/i/id` or `/activities/i/sets/j/id`;
- activity ids, activity orders and set ids must not repeat (`not_unique`, as PHP's `distinct`), and neither must set orders within one activity (new);
- the exercise must exist; **a retired exercise is accepted only on the activity that already has it**: it can stay in a workout's history but not be added, or swapped in (`not_found` at `/activities/i/exercise_id`);
- an existing activity may change its exercise; the change is stored;
- a completed set needs effort (`invalid_value` at `…/is_completed`); a heart-rate zone is a whole number from 1 to 5 (`out_of_range` at `…/difficulty_value`), retired exercises included.

Kept activities and sets are first moved to `order = -id` inside the transaction, so the new orders never collide with the old ones; the replace bumps `version`, so the response's `ETag` changes. The contract's shape checks (types, bounds, unknown fields, empty lists) run before all of this, as PHP's form request does; an empty `activities` or `sets` list is `required`, as Laravel's `required` treats an empty array.

The ordering differs from PHP's in one respect: PHP validates the payload before the 404, 403 and 412. Go validates it last, after the lock and the precondition, so a stale editor is told to reload before it is told what is wrong with data it will resend anyway, and the id checks read the rows the write will change.

## Consequences

- Good: no 500 from a payload; every rejected id is pointed at; a retired exercise cannot creep back into new training.
- Good: the editor's ETag flow is identical: the same tags (`"<id>-<version>"`), the same 412 and 428 bodies, verified against PHP with a scripted flow on both backends and with the web SPA's editor against Go.
- Bad: a client relying on PHP accepting a retired exercise, or ignoring an exercise change, behaves differently. Neither client does either: the pickers hide retired exercises and the editor does not change an existing activity's exercise.
- Done in [story 15](../../../../planning/epics/go-backend/stories/15-php-parity-fixes.md): the PHP API adopted the retired-exercise and set-order checks, stores a changed exercise and points a foreign set id at the set. The backends now differ only in the order of the checks (PHP validates the payload before the 404, 403 and 412).

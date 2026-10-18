# Workout Logging

Start workouts from templates, edit activities and sets, save progress, complete, repeat, and delete workouts.

## Current Behavior

1. User starts a workout from a template, which clones template activities and sets into a new `in_progress` workout.
2. User lands on **WorkoutEdit** and manages the full workout client-side before saving.
3. User can update effort and difficulty values, toggle set completion, remove sets, remove activities, and reorder activities.
4. User can add an activity by picking an exercise from a searchable catalog, which shows each exercise's description in two lines. It is appended to the end of the list with one blank set, and can be reordered like any other activity.
5. On **WorkoutEdit** and **WorkoutShow**, an info button beside an exercise name expands how to perform the exercise; it is collapsed by default and absent when the exercise has no description.
6. Save sends the full activities-and-sets payload in one bulk request.
7. Complete marks the workout as `completed` and redirects to **WorkoutShow**.
8. **WorkoutIndex** shows the user's workout history, newest updated first, 20 per page (`GET /workouts?page=N&per_page=M`, `per_page` 1–100), and **WorkoutShow** loads activities lazily.
9. User can repeat a completed workout, which creates a fresh `in_progress` copy.
10. User can delete a workout, which removes its activities and sets in the same transaction.

## iOS (`apps/ios`)

- **Dashboard tab**: the in-progress workout card (Continue), four summary tiles, the next-7-days schedule with Start Workout per slot (`POST /api/v1/workouts`, then the editor), and recent workouts. Pull to refresh; it reloads when returning from a pushed screen. A failed load shows a retry; a failed start shows a banner and stays on the dashboard
- The user's theme preference is applied app-wide and cached so the login screens use it on the next launch
- **Editor** (own in-progress workouts; anything else opens read-only): one card per activity with exercise, equipment, rest time and categories (an info button beside the name, when the exercise has a description, opens a sheet with how to perform it; the description is kept in the local draft), and a set grid with the same rules as the web — the difficulty column is hidden for the `none` unit, a 1–5 picker (or none) for heart-rate zones, a decimal field otherwise; effort is an integer (reps or seconds per the exercise); Done needs effort above zero and effort back to zero un-checks it; a completed set is locked until un-checked; Add set copies the previous set's values. A progress bar shows completed over total sets. Save sends the whole workout in one `PUT /workouts/{workoutId}/activities` with `If-Match` set to the last `ETag` it received and orders renumbered from 1, adopts the returned ids and `ETag` so the next save updates rows, and shows the 422 problem's `errors[].detail` under the set its pointer names (`/activities/0/sets/1/effort_value`)
- **Editor, activities**: the menu offers Add activity (searchable catalog picker showing two lines of each description; the exercise is appended with one blank set), Reorder activities (drag; orders renumbered on save) and Complete (saves first when dirty, then `POST /workouts/{workoutId}/complete`, then shows the completed workout). Removing a set asks for confirmation only when it is the activity's last set, which deletes the activity; the only remaining activity cannot be deleted. Leaving with unsaved changes asks to stay or leave
- **Local draft**: unsaved edits are written to Application Support about half a second after each change and when the app goes to the background. Reopening the editor restores them with an "Unsaved changes restored" banner and a Discard action, but only if the server copy's `ETag` still matches the one the draft was based on; a workout edited elsewhere drops the draft with a notice. Drafts are deleted on save, complete and sign-out
- **Workouts tab**: the history newest first (`GET /workouts?page=N&per_page=M`), loading the next page when the last row appears and stopping at the last page; pull to refresh; swipe to delete with confirmation, removing the row immediately and restoring it if the API refuses
- **Workout detail**: read-only activities and sets with the status badge; Edit/Continue only for the user's own in-progress workouts, Repeat workout only for their own completed ones (`POST /workouts/{workoutId}/repeat`, then the editor for the new workout). The dashboard's recent workouts and the editor's Complete land here

## Go API (`services/api-go`)

The Go backend serves every workout operation (history, start from a template, the editor's save, complete, repeat, delete) with the same responses as the PHP API, including the same `ETag` scheme (`"<id>-<version>"`), `If-Match` handling (428 when missing, 412 when stale) and error codes; the web editor works against it unchanged. The business rules above apply to both backends ([ADR 0010](../../services/api-go/docs/adr/0010-retired-exercises-rejected-on-write.md)); the one difference left is the order of the checks: PHP validates the payload before the 404, 403 and 412, Go after them.

The dashboard (`GET /dashboard`) is served by a Go read model that composes the catalog and training modules; the upcoming schedule, its ids (`{program_id}-{workout_template_id}-{date}`) and dates (next occurrence of each weekday, today included, at UTC midnight), the in-progress and recent workouts and the counts are the same as PHP's ([ADR 0011](../../services/api-go/docs/adr/0011-dashboard-read-model.md)).

## Business Rules

- Workout creation requires a valid `workout_template_id`.
- New workouts are always created in `in_progress` status.
- Repeated workouts do not keep a template reference. They set `workout_template_id` to `null`.
- Workout status is one-way: `in_progress` to `completed`.
- Reads are owner-scoped through the workout query builder. Non-owners receive 404 on every action (read, save, complete, repeat, delete); 403 is only for the owner's own workout in a status that forbids the action.
- Mutations are policy-guarded and require the current user to own the workout.
- Save is only allowed for `in_progress` workouts.
- Complete requires `in_progress` status.
- Repeat requires `completed` status.
- Save receives the full activities-and-sets payload and diffs it against persisted records.
- Activities omitted from the payload are deleted.
- Sets omitted from an activity payload are deleted.
- Activity IDs must belong to the target workout. Activity IDs, activity orders and set IDs must not repeat, and neither may set orders within one activity (422 `not_unique`, never a server error).
- Each activity is returned as `exercise_id` plus a nested `exercise` snapshot (`name`, `description`, `equipment_name`, `effort_type`, `difficulty_unit`, `category_names`, `rest_time_seconds`); the snapshot is omitted when the exercise is not loaded. Workouts carry `status` as the enum value; the clients render "In progress"/"Completed" and the effort and difficulty headers from their own catalogs.
- Integer ids in request bodies must be JSON integers: `true` or `"5"` is a 422 `invalid_format`.
- Set IDs must belong to the target activity; a foreign one is a 422 `not_found` at that set's `id`.
- Saves are conditional: the editor sends the workout's `ETag` as `If-Match`. A workout changed elsewhere since it was loaded (another tab, the other app) answers 412 `precondition_failed`, nothing is written, and the editor offers to reload; a save without `If-Match` answers 428. Every save and every completion changes the `ETag`.
- Activities added during editing have no ID and are created on save. They belong to the workout copy only and never modify the source template.
- Only active exercises are offered in the picker. Retired (soft-deleted) exercises are excluded, and the save rejects one (422 `not_found` at `exercise_id`) on a new activity or swapped onto an existing one; an activity that already has a retired exercise keeps it, and history still resolves it.
- An existing activity sent with a different `exercise_id` is saved with the new exercise.
- Frontend normalizes activity and set order before sending the payload.
- The save response returns persisted IDs, so a subsequent save updates the same rows instead of recreating them.
- A workout must retain at least one activity, and each activity must retain at least one set.
- Orders are 1–1000, `effort_value` is 0–100000 and `difficulty_value` is 0–10000 (heart-rate zones additionally 1–5); larger values are rejected with a 422 problem (`errors[].code` `out_of_range`) rather than overflowing the database columns.
- Completed sets must have `effort_value > 0`.
- A weight may be left blank. Shipped templates prescribe sets and repetitions but no weight, so a workout started from one opens with empty weight fields. A blank weight is stored as `null`, never as zero, and a set can be completed without one; an entered `0` is stored as zero.
- Start, repeat, save, and delete operations run in database transactions.
- Workout name is copied from the source template or source workout.

## Known Limitations

- No manual workout creation without a template.
- No reopen flow after completion.
- No rename, notes, comments, or duration tracking.
- No exercise substitution in the editors: an activity is removed and re-added instead. The API stores a changed `exercise_id`, but neither client sends one.
- No creating custom exercises. Added activities can only use exercises from the shared catalog.
- No sorting or filtering on workout history beyond newest updated first.
- No workout analytics such as volume, PRs, or summaries.
- No undo after deleting activities once changes are saved.

## Surface Area

- Pages: `WorkoutIndex`, `WorkoutShow`, `WorkoutEdit`
- Route names: `workouts.index`, `workouts.show`, `workouts.edit`, `workouts.store`, `workouts.activities.replace`, `workouts.complete`, `workouts.repeat`, `workouts.destroy`
- Full reference: [Pages & Routes](../pages-and-routes.md)

## Related

- [Programs](programs.md)
- [Architecture](../architecture.md)

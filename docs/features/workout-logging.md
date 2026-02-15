# Workout Logging

Users create workout sessions from templates, track them in real time, and manage their workout history.

## Current Behavior

1. **Start** — User clicks "Start Workout" on a template → POST creates a new workout cloned from the template (activities + sets copied, status = `in_progress`), redirects to edit page
2. **Track** — User edits the in-progress log on **WorkoutEdit** (see [Activity Tracking](activity-tracking.md))
3. **Complete** — User clicks "Complete" → POST transitions status to `completed`, redirects to the workout show page
4. **Review** — **WorkoutIndex** lists all user's workouts (paginated, 20/page, newest first). **WorkoutShow** shows a single workout with deferred activities
5. **Repeat** — On a completed workout, user clicks "Repeat" → POST creates a new in-progress copy with same activities/sets (weights and reps preserved, completion flags reset, `workout_template_id` set to null)
6. **Delete** — User deletes a workout → all activities and sets cascade-deleted in a transaction, redirects to index

## Business Rules

- Workout is always created from a template — `workout_template_id` is required and must exist
- Repeated workouts have `workout_template_id = null` (no template reference)
- Status lifecycle: `in_progress` → `completed` (one-way, no reopen)
- Complete requires `in_progress` status
- Repeat requires `completed` status
- All mutations (complete, repeat, delete) are policy-guarded: owner only
- All reads are owner-scoped via `WorkoutBuilder::ownedBy()` — non-owners get 404
- Clone operations (start, repeat) run in a DB transaction
- Workout name is copied from the template/source workout name

## Known Limitations

- No workout editing after completion (no reopen)
- No manual workout creation without a template
- No workout naming/renaming by user
- No workout notes or comments
- No duration tracking (start time, end time, total time)
- No workout date selection — always uses creation timestamp
- No sorting or filtering on workout index (always newest-updated first)
- No workout stats or summaries (total volume, PR tracking)

## Pages & Routes

| Page         | Route                     | Name             |
| ------------ | ------------------------- | ---------------- |
| WorkoutIndex | `GET /workouts`           | `workouts.index` |
| WorkoutShow  | `GET /workouts/{id}`      | `workouts.show`  |
| WorkoutEdit  | `GET /workouts/{id}/edit` | `workouts.edit`  |

| Action           | Method | Path                           | Name                | Guard                    |
| ---------------- | ------ | ------------------------------ | ------------------- | ------------------------ |
| Start workout    | POST   | `/workouts`                    | `workouts.store`    | Auth + FormRequest       |
| Complete workout | POST   | `/workouts/{workout}/complete` | `workouts.complete` | `WorkoutPolicy@complete` |
| Repeat workout   | POST   | `/workouts/{workout}/repeat`   | `workouts.repeat`   | `WorkoutPolicy@repeat`   |
| Delete workout   | DELETE | `/workouts/{workout}`          | `workouts.destroy`  | `WorkoutPolicy@delete`   |

## Related

- [Activity Tracking](activity-tracking.md) — editing activities and sets within a workout
- [Programs](programs.md) — browsing templates to start workouts from

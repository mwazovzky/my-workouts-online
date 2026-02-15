# Workout Logging

Users create workout sessions from templates, track them in real time, and manage their workout history.

## Current Behavior

1. **Start** — User clicks "Start Workout" on a template → POST creates a new workout log cloned from the template (activities + sets copied, status = `in_progress`), redirects to edit page
2. **Track** — User edits the in-progress log on **WorkoutLogEdit** (see [Activity Tracking](activity-tracking.md))
3. **Complete** — User clicks "Complete" → POST transitions status to `completed`, redirects to the workout show page
4. **Review** — **WorkoutLogIndex** lists all user's logs (paginated, 20/page, newest first). **WorkoutLogShow** shows a single log with deferred activities
5. **Repeat** — On a completed log, user clicks "Repeat" → POST creates a new in-progress copy with same activities/sets (weights and reps preserved, completion flags reset, `workout_template_id` set to null)
6. **Delete** — User deletes a log → all activities and sets cascade-deleted in a transaction, redirects to index

## Business Rules

- Workout log is always created from a template — `workout_template_id` is required and must exist
- Repeated logs have `workout_template_id = null` (no template reference)
- Status lifecycle: `in_progress` → `completed` (one-way, no reopen)
- Complete requires `in_progress` status
- Repeat requires `completed` status
- All mutations (complete, repeat, delete) are policy-guarded: owner only
- All reads are owner-scoped via `WorkoutLogBuilder::ownedBy()` — non-owners get 404
- Clone operations (start, repeat) run in a DB transaction
- Log name is copied from the template/source log name

## Known Limitations

- No workout log editing after completion (no reopen)
- No manual workout creation without a template
- No workout naming/renaming by user
- No workout notes or comments
- No duration tracking (start time, end time, total time)
- No workout date selection — always uses creation timestamp
- No sorting or filtering on workout log index (always newest-updated first)
- No workout stats or summaries (total volume, PR tracking)

## Pages & Routes

| Page | Route | Name |
|---|---|---|
| WorkoutLogIndex | `GET /workout-logs` | `workout.logs.index` |
| WorkoutLogShow | `GET /workout-logs/{id}` | `workout.logs.show` |
| WorkoutLogEdit | `GET /workout-logs/{id}/edit` | `workout.logs.edit` |

| Action | Method | Path | Name | Guard |
|---|---|---|---|---|
| Start workout | POST | `/workout-logs` | `workout.logs.store` | Auth + FormRequest |
| Complete workout | POST | `/workout-logs/{workoutLog}/complete` | `workout.logs.complete` | `WorkoutLogPolicy@complete` |
| Repeat workout | POST | `/workout-logs/{workoutLog}/repeat` | `workout.logs.repeat` | `WorkoutLogPolicy@repeat` |
| Delete workout | DELETE | `/workout-logs/{workoutLog}` | `workout.logs.destroy` | `WorkoutLogPolicy@delete` |

## Related

- [Activity Tracking](activity-tracking.md) — editing activities and sets within a log
- [Programs](programs.md) — browsing templates to start workouts from

# Activity Tracking

Users edit exercises and sets within an in-progress workout log.

## Current Behavior

1. On **WorkoutLogEdit**, user sees a list of activities (exercises) with their sets
2. Each set shows: order, repetitions, weight, completion toggle
3. User updates a set (reps, weight, completion) → PATCH sends the full sets array for that activity
4. User deletes an activity → DELETE removes the activity and its sets
5. Changes are saved immediately (no draft/save button — each action is a separate request)

## Business Rules

- Only workout-log activities can be updated or deleted — template activities are rejected by `ActivityPolicy`
- Activities in completed workout logs cannot be updated (service-level check, returns 422)
- Activity update replaces the entire sets collection for that activity:
  - Existing sets (with `id`) are updated in place
  - New sets (without `id`) are created
  - Sets missing from the payload are deleted
  - Orders are re-normalized to sequential 1-based values
- Set IDs in the payload must belong to the target activity (validated)
- Minimum 1 set required per update
- Set validation: `repetitions` ≥ 0 (integer), `weight` ≥ 0 (numeric), `order` ≥ 1 (integer, distinct), `is_completed` (boolean, optional)
- Activity deletion cascades to sets, runs in a transaction
- Owner check traverses `activity → workout_log → user_id`

## Known Limitations

- No adding new activities to an existing workout log
- No reordering activities within a log
- No exercise substitution (swap one exercise for another)
- No rest timer integration
- No set history/comparison with previous workouts
- No per-set notes
- No undo for activity deletion

## Pages & Routes

| Action | Method | Path | Name | Guard |
|---|---|---|---|---|
| Update activity (sets) | PATCH | `/activities/{activity}` | `activities.update` | `ActivityPolicy@update` |
| Delete activity | DELETE | `/activities/{activity}` | `activities.destroy` | `ActivityPolicy@delete` |

## Related

- [Workout Logging](workout-logging.md) — the workout log lifecycle that contains activities

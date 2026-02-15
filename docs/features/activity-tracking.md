# Activity Tracking

Users edit exercises and sets within an in-progress workout.

## Current Behavior

1. On **WorkoutEdit**, user sees a list of activities (exercises) with their sets
2. Each set shows: order, repetitions, weight, completion toggle
3. All editing (add/remove sets, toggle completion, update reps/weight, remove activities) is **client-side only**
4. A **Save** button appears when there are unsaved changes — sends all activities and sets in one bulk request
5. **Complete** saves any unsaved changes first, then marks the workout as completed
6. The last activity cannot be removed (trash icon is hidden)
7. Removing the last set from an activity prompts a confirmation, then removes the entire activity

## Business Rules

- Only in-progress workouts can be saved (`WorkoutPolicy@save`)
- The bulk save endpoint (`PATCH /workouts/{workout}/save`) receives the full activities+sets payload:
  - Existing activities (with `id`) are updated, new ones (without `id`) are created
  - Activities missing from the payload are deleted (along with their sets)
  - Within each activity, sets follow the same upsert/delete pattern
  - Set IDs must belong to the correct activity (validated)
  - Activity IDs must belong to the target workout (validated)
- Minimum 1 activity per workout, minimum 1 set per activity
- Frontend normalizes orders to sequential 1-based values before sending
- Set validation: `repetitions` ≥ 0 (integer), `weight` ≥ 0 (numeric), `order` ≥ 1 (integer), `is_completed` (boolean, optional)
- All operations run in a single database transaction
- Owner check via `WorkoutPolicy` — `user_id` must match authenticated user

## Known Limitations

- No adding new activities (exercises) to an existing workout from the edit page
- No reordering activities within a log
- No exercise substitution (swap one exercise for another)
- No rest timer integration
- No set history/comparison with previous workouts
- No per-set notes
- No undo for activity deletion (once saved)

## Pages & Routes

| Action              | Method | Path                       | Name            | Guard                |
| ------------------- | ------ | -------------------------- | --------------- | -------------------- |
| Save workout (bulk) | PATCH  | `/workouts/{workout}/save` | `workouts.save` | `WorkoutPolicy@save` |

## Related

- [Workout Logging](workout-logging.md) — the workout lifecycle that contains activities

# Activity Tracking

Users edit exercises and sets within an in-progress workout.

## Current Behavior

1. On **WorkoutEdit**, user sees a list of activities (exercises) with their sets
2. Each set shows: order, repetitions, weight, completion toggle
3. Activities can be **reordered via drag-and-drop** when more than one activity exists (drag handle visible on each card)
4. All editing (add/remove sets, toggle completion, update reps/weight, remove activities, reorder activities) is **client-side only**
5. A **Save** button appears when there are unsaved changes — sends all activities and sets in one bulk request
6. **Complete** saves any unsaved changes first, then marks the workout as completed
7. The last activity cannot be removed (trash icon is hidden)
8. Removing the last set from an activity prompts a confirmation, then removes the entire activity

## Business Rules

- Only in-progress workouts can be saved (`WorkoutPolicy@save`)
- The bulk save endpoint (`PATCH /workouts/{workout}/save`) receives the full activities+sets payload:
  - Existing activities (with `id`) are updated, new ones (without `id`) are created
  - Activities missing from the payload are deleted (along with their sets)
  - Within each activity, sets follow the same upsert/delete pattern
  - Set IDs must belong to the correct activity (validated)
  - Activity IDs must belong to the target workout (validated)
- Minimum 1 activity per workout, minimum 1 set per activity
- Frontend normalizes activity and set orders to sequential 1-based values before sending
- Activity order is preserved: existing activity orders are cleared (set to `-id`) before upsert to avoid unique constraint violations
- Set validation: `repetitions` ≥ 0 (integer), `weight` ≥ 0 (numeric), `order` ≥ 1 (integer), `is_completed` (boolean, optional)
- All operations run in a single database transaction
- Owner check via `WorkoutPolicy` — `user_id` must match authenticated user

## Known Limitations

- No adding new activities (exercises) to an existing workout from the edit page
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

# Workout Logging

Start workouts from templates, edit activities and sets, save progress, complete, repeat, and delete workouts.

## Current Behavior

1. User starts a workout from a template, which clones template activities and sets into a new `in_progress` workout.
2. User lands on **WorkoutEdit** and manages the full workout client-side before saving.
3. User can update effort and difficulty values, toggle set completion, remove sets, remove activities, and reorder activities.
4. Save sends the full activities-and-sets payload in one bulk request.
5. Complete marks the workout as `completed` and redirects to **WorkoutShow**.
6. **WorkoutIndex** shows the user's workout history, and **WorkoutShow** loads activities lazily.
7. User can repeat a completed workout, which creates a fresh `in_progress` copy.
8. User can delete a workout, which removes its activities and sets in the same transaction.

## Business Rules

- Workout creation requires a valid `workout_template_id`.
- New workouts are always created in `in_progress` status.
- Repeated workouts do not keep a template reference. They set `workout_template_id` to `null`.
- Workout status is one-way: `in_progress` to `completed`.
- Reads are owner-scoped through the workout query builder. Non-owners receive 404 responses.
- Mutations are policy-guarded and require the current user to own the workout.
- Save is only allowed for `in_progress` workouts.
- Complete requires `in_progress` status.
- Repeat requires `completed` status.
- Save receives the full activities-and-sets payload and diffs it against persisted records.
- Activities omitted from the payload are deleted.
- Sets omitted from an activity payload are deleted.
- Activity IDs must belong to the target workout.
- Set IDs must belong to the target activity.
- Frontend normalizes activity and set order before sending the payload.
- A workout must retain at least one activity, and each activity must retain at least one set.
- Completed sets must have `effort_value > 0`.
- Start, repeat, save, and delete operations run in database transactions.
- Workout name is copied from the source template or source workout.

## Known Limitations

- No manual workout creation without a template.
- No reopen flow after completion.
- No rename, notes, comments, or duration tracking.
- No exercise substitution or adding brand-new activities during editing.
- No sorting or filtering on workout history beyond newest updated first.
- No workout analytics such as volume, PRs, or summaries.
- No undo after deleting activities once changes are saved.

## Surface Area

- Pages: `WorkoutIndex`, `WorkoutShow`, `WorkoutEdit`
- Route names: `workouts.index`, `workouts.show`, `workouts.edit`, `workouts.store`, `workouts.save`, `workouts.complete`, `workouts.repeat`, `workouts.destroy`
- Full reference: [Pages & Routes](../pages-and-routes.md)

## Related

- [Programs](programs.md)
- [Architecture](../architecture.md)

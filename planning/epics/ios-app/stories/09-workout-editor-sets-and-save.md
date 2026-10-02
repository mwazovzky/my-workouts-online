# User Story: Workout Editor: Sets and Save

Edit the sets of an in-progress workout with the exact rules of the web editor, and save them in one request.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Logging sets is the core of the product. The iOS editor must mirror `apps/web/src/pages/WorkoutEdit.vue`, `components/Activity.vue` and `components/Set.vue` rule for rule, or users will get different behaviour on the two clients.

## User-Visible Outcome

Users open an in-progress workout, edit effort and difficulty per set, mark sets done, add sets, and save. The progress bar shows completed over total sets.

## Scope

- `WorkoutEditorModel` (`@Observable`, in `WorkoutsCore`) holding an editable `WorkoutDraft { workoutId, baseUpdatedAt, activities: [DraftActivity { id?, exerciseId, order, exercise metadata, sets: [DraftSet { id?, order, effortValue, difficultyValue?, isCompleted }] }] }` built from `getWorkout`
- editing allowed only when `status == in_progress` and `user_id` is the current user; otherwise the read-only detail is shown
- set row rules: difficulty column hidden when `exercise_difficulty_unit == none`; for `heart_rate_zone` a picker with "—" (nil) and 1–5; otherwise a decimal field (min 0, step 0.5, blank → nil); effort is an integer field (reps or seconds per `exercise_effort_type`), header from `exercise_effort_label` defaulting to "Reps"; done toggle enabled only when effort > 0; setting effort to 0 un-checks done; a completed set's fields are locked
- add set copies the previous set's values; with no previous set effort is 0 and difficulty 0 (nil for zone or none)
- activity card: exercise name, categories, equipment, rest time, add-set button
- progress bar: completed sets / total sets
- Save: renumber `order` from 1 for activities and sets, send `saveWorkout` with `{activities:[{id?, exercise_id, order, sets:[{id?, order, effort_value, difficulty_value, is_completed}]}]}`, adopt ids from the response so the next save updates rows; dirty flag cleared on success
- 422 errors mapped to the offending row by dot path (`activities.0.sets.1.effort_value`)
- `WorkoutRepository` with `getWorkout`, `saveWorkout`

## Non-Goals

- adding, deleting, reordering activities; complete; repeat; draft persistence (story 10)
- rest timer, notes

## Acceptance Criteria

- every rule above is enforced by the model and covered by a test
- the save payload matches `WorkoutSaveRequest` in the contract and orders are renumbered from 1
- after save, local ids equal the response ids and a second save sends those ids
- a 422 highlights the right set field with the API message
- a completed or foreign workout opens read-only

## Initial Tasks

- [ ] draft model and mapping from `Workout`
- [ ] editor model with all set rules
- [ ] views: editor, activity card, set row, progress bar
- [ ] save, id adoption, error mapping
- [ ] tests

## Test Cases

- done requires effort > 0; effort back to 0 un-checks; completed set locked
- zone picker bounds and nil; `none` hides difficulty; decimal field blank → nil
- new set copies previous; first set defaults per unit
- payload renumbering and id adoption
- 422 path mapping
- read-only for completed and foreign workouts

## Docs To Update

- `docs/features/workout-logging.md` (iOS editor parity)

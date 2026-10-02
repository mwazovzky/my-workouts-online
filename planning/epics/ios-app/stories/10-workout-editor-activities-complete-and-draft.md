# User Story: Workout Editor: Activities, Complete and Local Draft

Add, remove and reorder activities, complete and repeat workouts, and keep unsaved edits across an app kill.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: Platform (draft persistence)
- Creates New Feature: No

## Problem Statement

Real sessions diverge from templates, and phones kill backgrounded apps. The editor must support changing the activity list and must not lose unsaved sets when the app is killed mid-workout.

## User-Visible Outcome

Users add an exercise from a searchable picker, remove sets and activities, reorder activities, complete the workout, repeat a completed one, and find their unsaved edits restored after relaunching the app.

## Scope

- `ExercisePickerView` over `listExercises` (loaded on first open, searched by name client-side); adding appends an activity with one blank set, as the web does
- delete set: deleting the last set asks for confirmation and removes the activity; the only remaining activity cannot be deleted
- reorder activities with `.onMove` in edit mode; orders renumbered on save
- Complete: if dirty, save first, then `completeWorkout`, then replace the editor with the detail view
- Repeat (from detail, story 11 wires the button): `repeatWorkout` then push `.workoutEdit` for the new id
- unsaved-changes guard: leaving the editor with a dirty draft asks to discard or stay
- `WorkoutDraftStore` (actor): writes `WorkoutDraft` JSON to Application Support `Drafts/workout-{id}.json` with `.completeUntilFirstUserAuthentication` protection; saved debounced (about 500 ms) after every mutation and on `scenePhase == .background`
- on opening the editor: load the server workout; if a draft exists and `draft.baseUpdatedAt == workout.updated_at`, apply it and show a dismissible banner "Unsaved changes restored" with Discard; if the server is newer (edited on the web), delete the draft and say so
- drafts deleted on successful save, complete, delete, repeat (the new workout gets its own id) and on sign-out

## Non-Goals

- offline queueing of saves; exercise substitution; manual rest timer

## Acceptance Criteria

- picker search filters by name; added activity appears last with one blank set
- last-set deletion removes the activity after confirmation; the only activity cannot be deleted
- reordering changes the saved `order` values
- Complete on a dirty workout saves first, then completes, then shows the detail
- killing the app mid-edit and relaunching restores the sets with the banner; Discard reloads the server state
- a workout edited on the web after the draft was taken discards the draft with a message
- after save or complete the draft file is gone

## Initial Tasks

- [ ] exercise picker and add activity
- [ ] delete and reorder rules
- [ ] complete and repeat flows, unsaved-changes guard
- [ ] draft store, debounce, restore and discard
- [ ] tests

## Test Cases

- picker filtering; append with blank set
- last-set and only-activity rules
- reorder renumbering
- complete-with-dirty ordering (save before complete)
- draft round-trip, restore when `updated_at` matches, discard when newer, deletion after save/complete/sign-out

## Docs To Update

- `docs/features/workout-logging.md` (activities on iOS, draft behaviour)
- `docs/architecture.md` (draft store)

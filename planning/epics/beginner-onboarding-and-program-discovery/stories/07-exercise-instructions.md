# User Story: Exercise Instructions

Show how to perform an exercise where the user meets it, so a beginner does not have to guess from the name.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: Programs
- Creates New Feature: No

## Problem Statement

An exercise name such as "Dumbbell Romanian Deadlift" or "Cable Face Pull" tells a beginner little about what to do. Every shipped exercise already has a short how-to description in English and Russian (`catalog/exercises/*.yaml`), and `listExercises` returns it, but the screens where users meet exercises (the workout editor, a workout's page, a template's page) read the `ActivityExercise` snapshot embedded in each activity, which has no `description`. The text exists and is never shown.

## User-Visible Outcome

Next to each exercise name in a workout, in the editor and on a template's page, an info control reveals the exercise's description in the user's language. Exercises without a description show no control. The exercise picker shows the description of the exercise being chosen.

## Scope

- **Contract**: an optional `description: [string, 'null']` on `ActivityExercise`, localized like `name`; additive, so `info.version` becomes `2.1.0`, with the example updated.
- **PHP**: `ActivityResource` adds the exercise's localized description to the snapshot (from the already loaded exercise, so no new query; retired exercises included).
- **Go**: the `catalogapi` exercise lookup and template snapshot carry the localized description; the training and catalog HTTP adapters map it into the snapshot.
- **Web**: `Activity.vue` gets an info button beside the exercise name that expands the description inline, collapsed by default, on `WorkoutEdit`, `WorkoutShow` and `WorkoutTemplateShow`; `WorkoutEdit` copies `description` when it builds the snapshot of an exercise picked from the catalog; the picker shows the description under each name (or for the highlighted one); `npm run api:types`.
- **iOS**: `description` in the `Workout` domain model, the API mapping and `WorkoutDraft` (so it survives an offline draft); an info button on the activity card in `WorkoutEditorView` and `WorkoutTemplateDetailView` opening a sheet with the description, equipment, categories and rest time; the description in `ExercisePickerView`; strings in `Localizable.xcstrings` (en + ru).
- The control is absent when the description is null or blank.

## Non-Goals

- Images, GIFs or video: media need licensing, storage and content for every exercise in both languages, and are their own epic.
- A separate exercise detail endpoint or page (`GET /exercises/{id}`).
- Opening the description automatically the first time a user logs an exercise; a possible follow-up once the control exists.
- Writing descriptions for admin-created exercises; admins can already add them in the catalog editor.
- Changing description text in `catalog/`.

## Acceptance Criteria

- `GET /workouts/{workoutId}`, `PUT /workouts/{workoutId}/activities`, `POST /workouts`, `POST /workouts/{workoutId}/repeat` and `GET /workout-templates/{workoutTemplateId}` return `exercise.description` for each activity, in the `Accept-Language` locale, falling back to English; null when the exercise has none.
- PHP and Go return the same value for the same exercise and locale, retired exercises included.
- Redocly and Spectral lint pass; Schemathesis passes against both backends.
- Web: each activity with a description shows an info button with an accessible label; activating it toggles the text; activities without one show no button; the editor's set grid and its keyboard flow are unchanged.
- Web: an exercise added from the picker shows its description before the first save.
- iOS: the info button opens a sheet with the description; it works offline for a draft opened earlier; no button when there is no description.
- Russian users see the Russian description.

## Initial Tasks

- [ ] Contract: add `ActivityExercise.description`, update the example, bump `info.version` to `2.1.0`; lint both rulesets
- [ ] PHP: `ActivityResource` snapshot; `ActivityResourceTest`, `TranslatedResourceTest`
- [ ] Go: description in the `catalogapi` lookup and snapshot and both HTTP adapters; `make generate`, `make check`
- [ ] Web: `npm run api:types`; info toggle in `Activity.vue`; the picker snapshot in `WorkoutEdit.vue`; description in the picker; strings in both locale catalogs
- [ ] iOS: domain model, `APIMapping`, `WorkoutDraft`; info sheet in `WorkoutEditorView` and `WorkoutTemplateDetailView`; picker; `Localizable.xcstrings`
- [ ] Run `scripts/contract-test.sh` against PHP and Go
- [ ] Update docs

## Test Cases

- PHP `ActivityResourceTest`: description present, null when missing, localized for `ru` with English fallback, retired exercise still resolves it
- Go training and catalog HTTP tests (golden files): the same cases
- Web `Activity.test.js`: the button shows only with a description, toggles the text, has an accessible label; `WorkoutEdit.test.js`: a picked exercise carries its description; `WorkoutShow` and `WorkoutTemplateShow` render it
- iOS `WorkoutsCore` tests: mapping with and without a description; a `WorkoutDraft` round trip keeps it; UI test: the info sheet opens from the editor
- Schemathesis on both backends

## Docs To Update

- docs/features/workout-logging.md: the snapshot field list and the editor's activity card
- docs/features/programs.md: the template page

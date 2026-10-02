# User Story: Programs and Workout Templates

Browse programs, inspect their weekly schedule, enroll, and start a workout from a template.

## Feature Mapping

- Primary Feature: Programs
- Secondary Features: Workout Logging (start workout)
- Creates New Feature: No

## Problem Statement

Users cannot browse or enroll from the phone.

## User-Visible Outcome

A programs list with an enrolled filter and badge, a program detail with templates grouped by weekday and an Enroll button, and a read-only template detail with a Start workout action.

## Scope

- `ProgramListView` over `listPrograms` with a client-side "Enrolled only" toggle (same as the web switch)
- `ProgramDetailView` over `getProgram`: templates grouped by `pivot.weekday` using `weekday_label`; Enroll calls `enrollProgram` and flips `is_enrolled` optimistically, rolling back on failure
- `WorkoutTemplateDetailView` over `getWorkoutTemplate`: activities with exercise name, categories, equipment, rest time and planned sets with `exercise_effort_label` and `exercise_difficulty_label`; Start calls `createWorkout` and pushes `.workoutEdit`
- `ProgramRepository`, `TemplateRepository`
- loading, empty and error states from story 7

## Non-Goals

- unenroll (no endpoint), search, filters, program metadata

## Acceptance Criteria

- enroll updates the badge on both list and detail without a full reload; a failed enroll reverts and shows the error
- a 403 `email_unverified` returns the user to the verify gate
- template detail shows the labels exactly as the API localizes them; switching the device language switches them

## Initial Tasks

- [ ] repositories
- [ ] three views and view models
- [ ] optimistic enroll with rollback
- [ ] tests

## Test Cases

- list view model: empty, error, filter toggle
- enroll success, failure rollback, unverified redirect
- weekday grouping from fixtures

## Docs To Update

- `docs/features/programs.md` (iOS screens)
- `docs/pages-and-routes.md`

# User Story: Exercise Substitution

Allow users to substitute an exercise inside an in-progress workout when the original movement is not practical to perform.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Real gyms are imperfect. Equipment may be occupied, unavailable, or inappropriate for the user that day. If workouts cannot adapt, the app becomes brittle and less useful in real-world training.

## User-Visible Outcome

A user can replace a planned exercise with another exercise while keeping the workout usable and historically understandable.

## Scope

- substitute one activity's exercise inside an in-progress workout
- preserve workout continuity after substitution
- show the substituted exercise clearly in the workout
- define how historical review reflects the substitution

## Non-Goals

- automatic substitution recommendations
- template editing
- permanent modification of the source workout template
- advanced exercise-equivalence engine

## Acceptance Criteria

- user can choose a replacement exercise for an existing activity
- substitution affects only the current workout
- source template remains unchanged
- saved workout correctly reflects the substituted exercise
- workout history makes the performed exercise understandable
- authorization and ownership rules remain intact

## Initial Tasks

- define substitution UX and exercise selection flow
- define backend save behavior for changed exercise_id
- validate that substitution remains scoped to the current workout copy
- decide whether set data is preserved, reset, or partially preserved on substitution
- add tests for owner behavior, persistence, and template integrity
- update current-state docs after release

## Test Cases

- owner can substitute an exercise in an in-progress workout
- substituted exercise persists after save
- source workout template remains unchanged
- non-owner cannot perform substitution on another user's workout
- substitution behavior for existing set data is explicitly defined and tested
- completed workouts remain subject to existing mutation rules

## Docs To Update

- docs/features/workout-logging.md
- docs/architecture.md if workout-copy rules or save semantics change materially

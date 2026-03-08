# User Story: Previous-Performance Comparison

Show a comparison to the previous relevant workout so users can quickly judge improvement or regression.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Users should not have to manually inspect multiple workouts to understand whether they performed better than last time.

## User-Visible Outcome

A user can see the current workout compared with the most relevant previous workout or exercise performance.

## Scope

- define comparison baseline for first release
- show comparison on workout review surfaces
- make changes clear but not misleading

## Non-Goals

- advanced multi-period comparisons
- AI interpretation of performance changes
- coach recommendations

## Acceptance Criteria

- comparison baseline is clearly defined
- user can see meaningful deltas from the previous relevant performance
- comparison is accurate and easy to understand
- missing baseline cases are handled gracefully

## Initial Tasks

- define comparison rules and baseline lookup
- implement backend comparison data shaping
- add comparison UI to review surfaces
- add tests for baseline selection and delta correctness
- update current-state docs after release

## Test Cases

- comparison uses the correct previous baseline
- missing previous data is handled correctly
- values and deltas are accurate
- owner-scoped access remains intact

## Docs To Update

- docs/features/workout-logging.md
- docs/architecture.md if comparison rules become canonical

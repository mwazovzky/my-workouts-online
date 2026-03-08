# User Story: Volume Metrics Per Workout

Show workout volume metrics so users can better understand total training output for a session.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Users care not only about individual sets, but also about the overall amount of work completed in a workout.

## User-Visible Outcome

A user can see useful volume-oriented metrics for a completed workout.

## Scope

- define first-version volume metrics
- display metrics in workout review surfaces
- ensure calculations are consistent with existing data model

## Non-Goals

- advanced physiology metrics
- estimated fatigue models
- full periodization analysis

## Acceptance Criteria

- volume metrics are clearly defined and consistently calculated
- metrics appear in workout review where useful
- metrics are easy to interpret
- unsupported workout types are handled gracefully

## Initial Tasks

- define volume formulas for supported exercise/equipment combinations
- implement calculation logic
- expose values in workout resource or summary payload
- render UI on workout review page
- add tests for formula correctness and edge cases
- update current-state docs after release

## Test Cases

- supported workout data produces expected volume metrics
- unsupported or partial data follows defined fallback behavior
- metrics remain owner-scoped
- values stay consistent across review surfaces

## Docs To Update

- docs/features/workout-logging.md
- docs/architecture.md if calculation rules become canonical

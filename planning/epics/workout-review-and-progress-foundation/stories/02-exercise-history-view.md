# User Story: Exercise History View

Allow users to review performance history for a specific exercise so they can track progress over time.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Workout-level history is not enough when users want to understand whether a specific lift or movement is improving.

## User-Visible Outcome

A user can open an exercise-focused view and see relevant past performances for that exercise.

## Scope

- exercise-level history surface
- core metrics per historical entry
- clear ordering and filtering of exercise sessions

## Non-Goals

- full analytics dashboard
- AI insights
- public comparison with other users

## Acceptance Criteria

- user can access a history view for an exercise
- history entries are accurate and understandable
- entries are scoped to the current user
- history is useful for comparison, not just raw data dumping

## Initial Tasks

- define entry point to exercise history
- create query path for exercise-specific history
- shape resource payload for historical entries
- implement review UI
- add tests for scoping, ordering, and data correctness
- update current-state docs after release

## Test Cases

- owner sees their exercise history correctly
- non-owner cannot access another user's exercise history
- history ordering is correct
- missing or sparse history is handled gracefully

## Docs To Update

- docs/features/workout-logging.md
- docs/architecture.md if new query or reporting patterns are introduced

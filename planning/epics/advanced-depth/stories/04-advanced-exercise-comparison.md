# User Story: Advanced Exercise Comparison

Allow advanced users to compare exercise performance across multiple sessions with more control than the default history views.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Advanced users often want deeper comparison than simple previous-session deltas, especially for lifts they track closely.

## User-Visible Outcome

A user can compare exercise performance across a broader time horizon using richer comparison views.

## Scope

- advanced comparison view for selected exercises
- richer comparison dimensions than the default review surface
- comparisons remain user-scoped and understandable

## Non-Goals

- full sports-science analytics suite
- coach dashboards
- public comparison features

## Acceptance Criteria

- user can open an advanced comparison view for supported exercises
- comparisons are accurate and readable
- the feature provides more value than the simpler history view without overwhelming the user

## Initial Tasks

- define advanced comparison dimensions
- implement supporting queries or reporting logic
- design advanced comparison UI
- add tests for correctness and access control
- update current-state docs after release

## Test Cases

- advanced comparison uses correct historical data
- unsupported or sparse history states are handled clearly
- other users' data is never exposed

## Docs To Update

- docs/features/workout-logging.md
- docs/architecture.md

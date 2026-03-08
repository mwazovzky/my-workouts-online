# User Story: Estimated Duration

Show estimated workout duration so users can judge whether they have enough time to start and finish a workout.

## Feature Mapping

- Primary Feature: Programs
- Secondary Features: Workout Logging
- Creates New Feature: No

## Problem Statement

Time commitment is one of the main reasons users postpone or skip workouts. Without an estimate, starting a workout feels riskier than it should.

## User-Visible Outcome

A user can see an estimated workout duration before starting a workout and, if appropriate, while viewing the active workout.

## Scope

- estimated duration shown on workout templates
- optional duration display on active workouts
- estimate based on existing workout structure and available data

## Non-Goals

- exact duration prediction
- adaptive ML-based estimates
- historical personalization in the first version

## Acceptance Criteria

- user can see an estimated duration before starting a workout
- estimate is presented clearly as an estimate, not an exact promise
- estimate logic is consistent across supported views
- missing data cases are defined and handled

## Initial Tasks

- define estimation model using existing activity, set, and rest data
- compute or present estimated duration on template and workout views
- define fallback behavior when estimate quality is weak
- add tests for estimation output where calculation rules are deterministic
- update current-state docs after release

## Test Cases

- workout template shows an estimated duration when enough data exists
- estimate formatting is clear and user-friendly
- fallback behavior is correct when data is incomplete
- active workout duration display follows the defined rules

## Docs To Update

- docs/features/programs.md
- docs/features/workout-logging.md
- docs/architecture.md if duration calculation becomes a cross-cutting rule

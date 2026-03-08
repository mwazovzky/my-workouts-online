# User Story: Post-Workout Summary

Show a clear post-workout summary after completion so users immediately understand what they accomplished.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Finishing a workout should feel rewarding and informative. Right now, completion ends the session without a strong sense of closure or progress.

## User-Visible Outcome

After completing a workout, the user sees a summary of the completed session with key stats and notable moments.

## Scope

- summary shown immediately after workout completion
- summary includes useful session-level metrics
- summary is accessible later from workout review where appropriate

## Non-Goals

- deep long-term analytics
- social sharing
- coach-style recommendations

## Acceptance Criteria

- user sees a post-workout summary after completing a workout
- summary includes clearly defined metrics and labels
- summary is understandable without extra explanation
- summary data matches the completed workout records

## Initial Tasks

- define summary metrics for first release
- add summary data to completion/review flow
- implement post-completion UI
- add backend aggregation support if needed
- add tests for summary correctness and visibility
- update current-state docs after release

## Test Cases

- completed workout shows a summary
- summary values match saved workout data
- empty or unusual workout shapes are handled correctly
- non-owner cannot access another user's summary beyond existing access rules

## Docs To Update

- docs/features/workout-logging.md
- docs/pages-and-routes.md if user flow changes materially

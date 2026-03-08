# User Story: Next-Workout Prompt

Show the user what to do next so they can move from enrollment to action with less hesitation.

## Feature Mapping

- Primary Feature: Programs
- Secondary Features: Workout Logging
- Creates New Feature: No

## Problem Statement

After enrolling or returning to the app, users may not know what workout to start next. That uncertainty creates avoidable friction.

## User-Visible Outcome

A user sees a clear next-workout prompt or recommended next action.

## Scope

- next-workout guidance based on current app state
- visible prompt in a relevant location
- clear CTA into the next workout or next meaningful action

## Non-Goals

- fully automated training plan adaptation
- calendar scheduling engine
- push-based reminder system in the same story

## Acceptance Criteria

- eligible users see a next-workout prompt
- prompt logic is clearly defined
- prompt leads to a meaningful next action
- users without a clear next workout see an appropriate fallback state

## Initial Tasks

- define next-workout logic
- choose display surface for the prompt
- implement backend or UI state shaping
- add tests for prompt eligibility and fallback states
- update current-state docs after release

## Test Cases

- users with an obvious next workout see the expected prompt
- users without enough context see a sensible fallback state
- prompt remains consistent with enrollment and workout history state

## Docs To Update

- docs/features/programs.md
- docs/features/workout-logging.md if workflow entry points change materially
- docs/product.md if this becomes part of the main user flow

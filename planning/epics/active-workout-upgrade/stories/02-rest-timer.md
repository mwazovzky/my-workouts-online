# User Story: Rest Timer

Allow users to run a rest timer during a workout so they do not need to leave the app between sets.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Many gym users rely on a timer between sets. If the app does not support that workflow, users switch to another app or the phone clock, which weakens engagement and makes the product feel incomplete.

## User-Visible Outcome

A user can start, stop, reset, and rerun a rest timer while working through a workout.

## Scope

- rest timer available on workout edit
- clear start, pause, reset behavior
- visible countdown state
- timer continues to behave predictably while the user moves through the workout UI

## Non-Goals

- background push notifications
- smartwatch integration
- fully personalized interval programming
- timer analytics

## Acceptance Criteria

- user can start a rest timer from the workout flow
- user can pause and reset the timer
- timer state is visible and understandable
- timer works reliably across common in-page interactions
- timer does not block workout save or completion actions

## Initial Tasks

- design rest timer UI placement and controls
- implement client-side timer behavior
- define whether timer length is fixed, user-controlled, or exercise-driven
- connect timer with existing workout edit page without disrupting save flow
- add UI tests or interaction coverage where practical
- update current-state docs after release

## Test Cases

- timer starts and counts down correctly
- pause and reset behave correctly
- timer does not corrupt unsaved workout changes
- timer state remains understandable after save and completion actions
- timer behavior on page navigation or refresh is explicitly defined and tested where applicable

## Docs To Update

- docs/features/workout-logging.md
- docs/product.md if the product surface changes materially

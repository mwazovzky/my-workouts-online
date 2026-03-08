# User Story: Custom Workout Creation

Allow users to create workouts without relying on a predefined template.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Some advanced users want to build workouts from scratch instead of only adapting template-based sessions.

## User-Visible Outcome

A user can create a custom workout and populate it with activities and sets.

## Scope

- create a user-defined workout
- define activities and sets during creation or editing
- keep custom workout separate from shared templates

## Non-Goals

- full programming platform
- advanced scheduling engine
- collaborative workout planning

## Acceptance Criteria

- user can create a custom workout
- custom workout can be edited and completed using supported flows
- custom workout remains clearly distinct from shared template-based workouts
- owner and policy rules remain intact

## Initial Tasks

- define custom workout creation flow
- extend data model or lifecycle rules as needed
- integrate with workout editing and completion flow
- add tests for creation, ownership, and persistence
- update current-state docs after release

## Test Cases

- owner can create a custom workout
- custom workout can be saved and completed
- non-owner cannot access another user's custom workout beyond existing rules
- template-related assumptions do not break when workout_template_id is absent by design

## Docs To Update

- docs/features/workout-logging.md
- docs/pages-and-routes.md
- docs/architecture.md

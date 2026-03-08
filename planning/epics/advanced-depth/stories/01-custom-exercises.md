# User Story: Custom Exercises

Allow users to create personal exercises so the app can reflect movements that are not in the shared catalog.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Advanced users often perform exercises that do not exist in the default catalog or need personal naming conventions.

## User-Visible Outcome

A user can create and use custom exercises in their own workouts.

## Scope

- create personal custom exercises
- use them in supported workout flows
- keep them scoped to the creating user

## Non-Goals

- shared marketplace of user-created exercises
- admin moderation system
- rich exercise programming metadata in first release

## Acceptance Criteria

- user can create a custom exercise
- custom exercise can be selected in supported workout flows
- other users cannot see or use that custom exercise unless explicitly supported later
- custom exercise behavior fits existing effort/equipment rules

## Initial Tasks

- define custom exercise data model
- define ownership and visibility rules
- integrate custom exercises into selection flows
- add tests for visibility, persistence, and usage
- update current-state docs after release

## Test Cases

- user can create a valid custom exercise
- user can use their custom exercise where supported
- other users cannot access it
- invalid effort/equipment combinations are rejected as defined

## Docs To Update

- docs/features/workout-logging.md
- docs/architecture.md

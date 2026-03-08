# User Story: Manual Activity Insertion

Allow users to add a new activity to an in-progress workout when they perform extra work not present in the original template.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Users frequently add accessory work, warm-up movements, or unplanned exercises. If the app cannot represent those changes, the workout log becomes incomplete and less trustworthy.

## User-Visible Outcome

A user can add a new activity with sets to an in-progress workout without modifying the source template.

## Scope

- add a new activity during workout editing
- choose an exercise for the new activity
- create and edit sets for the new activity
- persist the inserted activity inside the workout only

## Non-Goals

- creating brand-new custom exercises in the same story
- modifying the source template
- automatic program adaptation

## Acceptance Criteria

- user can add a new activity to an in-progress workout
- user can save and later review the inserted activity
- inserted activity belongs only to the workout copy
- inserted activity fits the existing order and validation rules
- source template remains unchanged

## Initial Tasks

- design add-activity entry point in workout edit
- support creating new activities in save payloads and service logic
- define exercise picker behavior using existing exercise data
- define ordering behavior for inserted activities
- add tests for persistence, ordering, and authorization
- update current-state docs after release

## Test Cases

- owner can add a new activity to an in-progress workout
- new activity persists after save
- inserted activity appears in workout show and history views as defined
- ordering stays valid after insertion
- non-owner cannot insert an activity into another user's workout
- template remains unchanged after insertion

## Docs To Update

- docs/features/workout-logging.md
- docs/architecture.md if activity lifecycle rules expand materially

# User Story: Personal Workout Templates

Allow users to save reusable personal workout templates so they can repeat custom structures efficiently.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: Programs
- Creates New Feature: No

## Problem Statement

Custom workouts are more useful when users can save and reuse them rather than rebuilding similar sessions repeatedly.

## User-Visible Outcome

A user can create and reuse personal workout templates separate from the shared catalog.

## Scope

- create personal templates
- start workouts from personal templates
- keep personal templates scoped to the owning user

## Non-Goals

- publishing personal templates to others
- community template marketplace
- coach distribution workflows

## Acceptance Criteria

- user can create a personal template
- user can start a workout from that template
- personal templates are private by default
- personal templates do not alter the shared catalog

## Initial Tasks

- define personal template model and ownership rules
- implement create/list/start flows
- integrate into existing workout-start flow where appropriate
- add tests for privacy, persistence, and start behavior
- update current-state docs after release

## Test Cases

- user can create and reuse a personal template
- user can start a workout from that template
- other users cannot access the personal template
- shared templates remain unaffected

## Docs To Update

- docs/features/workout-logging.md
- docs/features/programs.md if navigation or template discovery overlaps materially
- docs/architecture.md

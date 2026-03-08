# User Story: PR Detection and Celebration

Detect personal records and highlight them so users get clear, motivating feedback when they improve.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Users often do not realize when they have set a new personal best unless they manually compare sessions. The app should surface those moments automatically.

## User-Visible Outcome

The app identifies qualifying personal records and celebrates them in a lightweight, motivating way.

## Scope

- define first-version PR rules
- detect PRs on completed workouts
- show PR moments in review surfaces

## Non-Goals

- complex PR taxonomy for every training style
- public leaderboards
- social competition features

## Acceptance Criteria

- PR logic is clearly defined for first release
- qualifying workouts show PR moments consistently
- PR signaling is celebratory but not noisy
- false positives are minimized through explicit rules

## Initial Tasks

- define PR calculation rules
- implement PR detection service or query logic
- expose PR data in workout review surfaces
- design celebration UI
- add tests for PR edge cases and correctness
- update current-state docs after release

## Test Cases

- first qualifying PR is detected correctly
- non-qualifying workouts do not trigger PRs
- repeated equal values behave according to defined rules
- PRs are scoped to the current user only

## Docs To Update

- docs/features/workout-logging.md
- docs/architecture.md if PR rules become a durable cross-cutting concern

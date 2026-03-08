# User Story: Workout Reminders

Allow users to receive workout reminders so the app helps them return consistently between sessions.

## Feature Mapping

- Primary Feature: Motivation & Accountability
- Secondary Features: Auth & Profile
- Creates New Feature: Yes

## Problem Statement

A useful workout product should not disappear from a user's routine between sessions. Reminders can help sustain consistency if they are respectful and well-timed.

## User-Visible Outcome

A user can opt into workout reminders and receive them according to supported rules.

## Scope

- reminder preferences
- reminder scheduling rules for first release
- user-facing reminder state and controls

## Non-Goals

- complex notification orchestration
- coach-managed reminder plans
- aggressive re-engagement campaigns

## Acceptance Criteria

- user can opt into supported reminder behavior
- reminder preferences are stored and respected
- reminders are understandable and easy to control
- reminder behavior does not feel spammy in normal use

## Initial Tasks

- define reminder triggers and preference model
- implement preference UI
- implement reminder delivery mechanism for supported channels
- add tests for opt-in state and trigger logic
- update current-state docs after release

## Test Cases

- opted-in user receives supported reminders under defined conditions
- opted-out user does not receive reminders
- preference changes persist correctly
- reminder triggers behave according to rules

## Docs To Update

- create docs/features/motivation-and-accountability.md
- docs/features/auth-and-profile.md if preferences live there
- docs/product.md if reminders materially change the product surface

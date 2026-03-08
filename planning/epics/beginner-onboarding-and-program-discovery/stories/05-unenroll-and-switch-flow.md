# User Story: Unenroll and Switch Flow

Allow users to leave a program or switch to another one so program choice never feels permanent or trapping.

## Feature Mapping

- Primary Feature: Programs
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Enrollment currently feels incomplete because users can join a program but cannot cleanly leave or switch when their needs change.

## User-Visible Outcome

A user can unenroll from a program or switch to another program through an intentional flow.

## Scope

- explicit unenroll action
- defined switch behavior
- clear user messaging around impact on current or historical workouts

## Non-Goals

- complex migration of historical workouts
- program-transfer automation across all edge cases
- admin moderation workflow

## Acceptance Criteria

- user can unenroll from a program
- switch behavior is defined clearly if supported in the first release
- historical workouts remain understandable and intact
- enrollment state updates correctly across relevant views

## Initial Tasks

- define enrollment lifecycle rules
- implement unenroll or switch mutations
- update program state exposure in UI
- add tests for owner behavior and state transitions
- update current-state docs after release

## Test Cases

- user can unenroll from a program successfully
- enrollment state updates on program list and detail views
- historical workouts remain accessible and correct
- repeated unenroll or invalid switch attempts are handled correctly

## Docs To Update

- docs/features/programs.md
- docs/pages-and-routes.md

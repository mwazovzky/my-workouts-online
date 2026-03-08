# User Story: Save-State Feedback

Improve save and completion feedback so users always understand whether their workout progress is saved.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

The current bulk-save workflow is powerful, but users may not feel confident about save state, unsaved changes, or what happens when they complete a workout. Uncertainty in this area directly harms trust.

## User-Visible Outcome

A user can clearly see whether changes are unsaved, saving, saved, or failed, and completion behavior feels predictable.

## Scope

- explicit unsaved changes state
- visible saving state
- visible saved state
- clear error state on failed save
- clear interaction between save and complete actions

## Non-Goals

- full offline mode
- background sync engine
- automatic conflict resolution across devices

## Acceptance Criteria

- user can tell when the workout has unsaved changes
- user sees when a save is in progress
- user sees when a save succeeds or fails
- complete action behaves predictably when unsaved changes exist
- failed saves do not silently discard user input

## Initial Tasks

- audit current save UX on workout edit
- define save-state model and visible states
- implement UI feedback states
- define and implement complete-action behavior when unsaved changes exist
- add tests for save success, failure, and completion interactions
- update current-state docs after release

## Test Cases

- unsaved state appears after changing workout data
- save success resets unsaved state correctly
- failed save leaves user data intact and visible
- complete action with unsaved changes follows the defined behavior
- save-state UI remains correct after repeated edits and saves

## Docs To Update

- docs/features/workout-logging.md

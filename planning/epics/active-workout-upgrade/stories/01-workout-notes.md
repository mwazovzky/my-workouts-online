# User Story: Workout Notes

Allow users to record notes on an in-progress workout so they can capture context such as pain, fatigue, substitutions, or reminders.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Users often need to remember session-specific context that raw reps and weight do not capture. Without notes, the app loses information that matters for future decisions and workout review.

## User-Visible Outcome

A user can add, edit, save, and later review notes attached to a workout.

## Scope

- note field on the workout edit page
- note persists with the workout
- note is visible on the workout show page
- note can be edited while the workout is in progress

## Non-Goals

- per-set notes
- coach comments
- rich text editing
- public or shared notes

## Acceptance Criteria

- user can enter a note on an in-progress workout
- note is saved with the workout
- note appears on the workout details page after save
- owner can update their own workout note
- non-owner cannot read or update another user's note beyond existing access rules
- completed-workout note behavior is explicitly defined and enforced

## Initial Tasks

- add note storage to workouts
- update validation and save payload handling
- expose note in workout resources
- add note UI to workout edit and workout show
- add tests for owner, non-owner, and persistence behavior
- update current-state docs after release

## Test Cases

- owner can save a note on an in-progress workout
- owner sees the saved note on the workout show page
- updating the note overwrites the previous value
- empty note handling is defined and tested
- non-owner cannot access the note through workout pages
- if completed workouts are immutable, note updates on completed workouts fail correctly

## Docs To Update

- docs/features/workout-logging.md
- docs/pages-and-routes.md if route or payload behavior changes
- docs/architecture.md if model or resource patterns change materially

# User Story: Workout History and Detail

Browse past workouts, inspect one, and edit, repeat or delete it.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Users cannot see their history or manage past workouts from the phone.

## User-Visible Outcome

A paginated history list (newest first, loads more on scroll), a workout detail with status, activities and sets, and the actions Edit (own, in progress), Repeat (own, completed) and Delete (with confirmation).

## Scope

- `WorkoutListView` over `listWorkouts?page=N`: `Page<Workout>` with `loadMore` when `meta.current_page < meta.last_page`, loading footer, pull to refresh
- swipe to delete with confirmation; `deleteWorkout`; optimistic removal with rollback on error
- `WorkoutDetailView` over `getWorkout`: read-only activities and sets, status badge, Edit pushes `.workoutEdit`, Repeat uses the flow from story 10
- the Workouts tab opens the list; dashboard recent workouts push the same detail

## Non-Goals

- filters, search, date ranges, analytics

## Acceptance Criteria

- scrolling loads page 2 exactly once per page and stops at the last page
- delete removes the row immediately and rolls back on error
- Edit appears only for own in-progress workouts, Repeat only for own completed ones
- repeat navigates to the new workout's editor

## Initial Tasks

- [ ] pagination model and list view
- [ ] delete with confirmation and rollback
- [ ] detail view and action visibility
- [ ] tests

## Test Cases

- pagination model: first page, load more, last page stops
- delete success and rollback
- action visibility matrix (owner × status)

## Docs To Update

- `docs/features/workout-logging.md` (history on iOS)
- `docs/pages-and-routes.md`

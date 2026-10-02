# User Story: App Shell and Dashboard

Give signed-in users the four-tab shell and a dashboard that mirrors the web home page.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: Programs, Platform
- Creates New Feature: No

## Problem Statement

Signed-in users land on a placeholder. There is no navigation and no overview of what to do next.

## User-Visible Outcome

Four tabs (Dashboard, Programs, Workouts, Profile). The dashboard shows the upcoming schedule, the in-progress workout, recent workouts and the four summary counters, and lets the user start or continue a workout.

## Scope

- `MainTabView` with one `NavigationStack` per tab and an `AppRoute: Hashable` enum (`.program(id)`, `.workoutTemplate(id)`, `.workout(id)`, `.workoutEdit(id)`); no Admin tab regardless of `is_admin`
- shared components: `LoadingStateView`, empty state, error banner with retry, pull to refresh; a toast-style confirmation for short messages
- `DashboardView` and view model over `getDashboard`: `upcoming_schedule` (tap starts the template's workout via `createWorkout` and pushes `.workoutEdit`), `in_progress_workout` card (continue), `recent_workouts`, `summary` tiles
- refresh on appear and after returning from the editor
- theme from `user.theme_preference` applied with `.preferredColorScheme` and cached in `UserDefaults` for the pre-login screens

## Non-Goals

- the editor itself (stories 9 and 10; until then `.workoutEdit` shows the read-only workout or a placeholder)
- charts, streaks

## Acceptance Criteria

- all four tabs render and keep their own navigation state when switching
- `in_progress_workout == null` hides the card; counters match the API fixture
- starting from the schedule creates a workout and navigates to the editor route
- an API error shows the banner and retry reloads

## Initial Tasks

- [ ] tab shell and routes
- [ ] shared state components
- [ ] dashboard view and view model
- [ ] theme application
- [ ] tests

## Test Cases

- dashboard view model with and without an in-progress workout, empty schedule, error state
- theme mapping (`light`, `dark`, `system`)
- UI smoke: tabs render after login

## Docs To Update

- `docs/pages-and-routes.md` (iOS screens)
- `docs/features/workout-logging.md` (dashboard on iOS)

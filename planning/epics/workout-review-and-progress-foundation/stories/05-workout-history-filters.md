# User Story: Workout History Filters

Allow users to filter workout history so they can find relevant past sessions quickly.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

A flat reverse-chronological history becomes less useful as the number of workouts grows.

## User-Visible Outcome

A user can filter workout history by relevant criteria and quickly find the sessions they need.

## Scope

- define first release filters
- apply filters on workout history page
- preserve existing owner-scoped behavior

## Non-Goals

- advanced saved searches
- cross-user analytics
- complex reporting builder

## Acceptance Criteria

- user can apply supported filters on workout history
- filtered results are accurate
- filter UI is clear and does not overwhelm the page
- filters work well with pagination

## Initial Tasks

- choose supported filter dimensions
- extend query builder with filter scopes
- update controller and resource payloads as needed
- implement filter UI
- add tests for filtering correctness and scoping
- update current-state docs after release

## Test Cases

- each supported filter returns correct results
- combined filters behave correctly if supported
- non-owner cannot use filters to discover inaccessible workouts
- pagination remains correct under filtering

## Docs To Update

- docs/features/workout-logging.md
- docs/pages-and-routes.md if query parameters become part of the route contract

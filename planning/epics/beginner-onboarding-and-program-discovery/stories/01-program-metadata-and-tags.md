# User Story: Program Metadata and Tags

Add clear metadata and tags to programs so users can understand what each program is for before enrolling.

## Feature Mapping

- Primary Feature: Programs
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Programs are hard to evaluate when users cannot quickly tell difficulty, goals, time commitment, or training style.

## User-Visible Outcome

A user can see structured metadata on programs that helps them choose the right one.

## Scope

- define first-version program metadata
- display metadata on program list and detail views
- ensure metadata is understandable and consistent

## Non-Goals

- complex recommendation engine
- admin program builder redesign
- social reviews or ratings

## Acceptance Criteria

- user can see key metadata on programs
- metadata labels are clear and consistent
- metadata supports future filtering and recommendation work
- missing metadata cases are handled explicitly

## Initial Tasks

- define first release metadata fields
- add storage or presentation strategy for metadata
- update program resources and views
- add tests for rendering and data exposure where appropriate
- update current-state docs after release

## Test Cases

- program list shows defined metadata correctly
- program detail shows defined metadata correctly
- metadata is exposed consistently for authenticated users
- incomplete metadata follows defined fallback behavior

## Docs To Update

- docs/features/programs.md
- docs/product.md if the product surface changes materially

# User Story: Starter Program Recommendation

Recommend a suitable starter program so beginners can move from uncertainty to action faster.

## Feature Mapping

- Primary Feature: Programs
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Even with better metadata, many beginners still need a clear starting suggestion instead of a full decision space.

## User-Visible Outcome

A user receives a recommended starter program based on defined rules.

## Scope

- first-version recommendation rules
- recommendation presented in a clear, lightweight way
- recommendation can guide enrollment and first workout actions

## Non-Goals

- ML-based recommendation engine
- highly personalized long-term planning
- coach-level personalization

## Acceptance Criteria

- recommendation logic is explicitly defined
- eligible users can see a starter recommendation
- recommendation explains enough to feel credible
- user can accept or ignore the recommendation without friction

## Initial Tasks

- define recommendation rules and inputs
- implement recommendation service or query logic
- design recommendation UI
- connect recommendation to enrollment or next steps
- add tests for rule correctness and user flow
- update current-state docs after release

## Test Cases

- users with defined input conditions receive the correct recommendation
- users can proceed whether they accept or ignore the recommendation
- recommendation is stable and understandable under supported conditions

## Docs To Update

- docs/features/programs.md
- docs/product.md if recommendation becomes part of the activation flow

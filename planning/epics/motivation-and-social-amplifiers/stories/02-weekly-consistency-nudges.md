# User Story: Weekly Consistency Nudges

Provide light weekly nudges so users stay aware of their training consistency without feeling pressured.

## Feature Mapping

- Primary Feature: Motivation & Accountability
- Secondary Features: Workout Logging
- Creates New Feature: No

## Problem Statement

Users often benefit from a simple reminder of how their week is going, especially when consistency is the real goal.

## User-Visible Outcome

A user sees or receives a weekly consistency nudge based on supported workout activity rules.

## Scope

- define weekly consistency logic
- present nudges in a respectful, lightweight way
- connect nudges with the user's current workout behavior

## Non-Goals

- punitive streak mechanics
- coach messaging system
- full behavioral psychology engine

## Acceptance Criteria

- weekly nudge logic is clearly defined
- users receive or see nudges only under supported conditions
- nudges are useful, not noisy or guilt-driven

## Initial Tasks

- define consistency and nudge logic
- choose delivery surfaces
- implement rendering or scheduling behavior
- add tests for trigger conditions
- update current-state docs after release

## Test Cases

- eligible users see or receive the correct nudge
- ineligible users do not see unsupported nudges
- nudge messaging matches the user's actual weekly state

## Docs To Update

- docs/features/motivation-and-accountability.md
- docs/features/workout-logging.md if workout-state triggers become user-visible behavior
- docs/product.md if nudges become a visible product capability

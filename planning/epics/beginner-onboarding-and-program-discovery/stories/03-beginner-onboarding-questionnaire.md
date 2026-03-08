# User Story: Beginner Onboarding Questionnaire

Ask a small set of onboarding questions so beginners can be guided toward a suitable starting point.

## Feature Mapping

- Primary Feature: Programs
- Secondary Features: Auth & Profile
- Creates New Feature: No

## Problem Statement

Beginners often do not know how to choose a program. Without structured guidance, they may pick something unsuitable or abandon the app.

## User-Visible Outcome

A new user can answer a short onboarding questionnaire that helps narrow the right starting direction.

## Scope

- short onboarding question flow
- capture a small set of user goals or constraints
- connect answers to program recommendation logic later or immediately

## Non-Goals

- deep fitness assessment
- adaptive coaching logic
- full user profile overhaul

## Acceptance Criteria

- onboarding questions are short and easy to answer
- answers are stored or used in a defined way
- onboarding does not block the user excessively
- users can understand why the questions matter

## Initial Tasks

- define question set and response model
- determine when onboarding is shown
- store onboarding answers if needed
- implement UI flow
- add tests for flow completion and persistence
- update current-state docs after release

## Test Cases

- eligible users can complete onboarding flow
- answers persist or are consumed as defined
- users can continue to product use after onboarding
- skipping behavior is explicitly defined and tested if supported

## Docs To Update

- docs/features/programs.md
- docs/features/auth-and-profile.md
- docs/product.md if onboarding becomes part of the core product flow

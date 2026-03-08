# Epic: Beginner Onboarding and Program Discovery

Help new users choose the right program, understand what to do next, and start their first meaningful workout with low friction.

## Why This Epic Exists

The current product assumes users already understand program structure and can self-select a training path. That is a weak assumption for beginners and a growth bottleneck for the product.

Current gaps this epic addresses:

- No guided onboarding
- No program metadata such as difficulty, goal, or time commitment
- No search, filter, or sort on programs
- No starter recommendations
- No unenroll or switch-program flow
- No strong next-workout guidance

These gaps matter because acquisition and activation depend on helping users make a good first choice quickly.

## Target Audience

Primary: beginners

Secondary: mixed users who want clearer program selection

Tertiary: intermediate users who may want to switch programs more intentionally

## Expected Product Diff

Before this epic:

- users browse a flat list of programs with limited guidance
- program choice is harder than it should be
- the app gives weak support for activation after enrollment

After this epic:

- users understand which program fits them
- onboarding helps reduce hesitation and confusion
- the product creates a clearer path from signup to first workout

## Scope

This epic includes:

- onboarding flow or onboarding questions
- program metadata
- search, filter, and sort for programs
- starter recommendations
- unenroll or switch-program flow
- next-workout guidance

## Non-Goals

This epic does not include:

- full coaching personalization
- marketplace/community discovery
- algorithm-heavy recommendation systems
- trainer-assigned programming workflows

## Candidate User Stories

1. Program metadata and tags
2. Program filters and search
3. Beginner onboarding questionnaire
4. Starter program recommendation
5. Unenroll and switch flow
6. Next-workout prompt

## Sequencing Rationale

- Start with metadata because discovery improvements depend on structured program information.
- Add filters and search next because they are only useful once metadata exists.
- Add onboarding and recommendations after the program catalog is easier to reason about.
- Add switch-flow and next-workout guidance after the core onboarding path is stable.

## Success Metrics

Primary signals:

- higher enrollment-to-first-workout conversion
- higher percentage of new users completing a first workout
- lower early drop-off after signup

Secondary signals:

- search and filter usage
- recommendation acceptance rate
- reduced time from signup to first completed workout

## Risks

- too many onboarding questions can create friction instead of reducing it
- weak metadata can make recommendations feel arbitrary
- discovery improvements can become noisy if the catalog remains sparse

## Done Criteria

This epic is done when:

- new users can make a confident program choice with less friction
- the product provides a clearer path from signup to first workout
- relevant current-state docs are updated
- activation metrics improve or the assumptions are re-evaluated

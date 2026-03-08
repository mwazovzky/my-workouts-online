# Epic: Advanced Depth

Add optional power-user capabilities that make the product more flexible and valuable for serious lifters without making the default experience noisy.

## Why This Epic Exists

As the product becomes stronger for regular users, advanced users will want more control, more analysis, and more ways to adapt the system to their training style.

Current gaps this epic addresses:

- No custom exercises
- No custom workout creation
- No personal templates
- Limited workout flexibility beyond the planned core upgrades
- No deeper analytics for serious training review

These gaps matter because advanced users are often the most demanding, the most vocal, and the most likely to recommend a product if it genuinely supports their workflow.

## Target Audience

Primary: advanced lifters

Secondary: intermediate users who grow into more customized training needs

## Expected Product Diff

Before this epic:

- the app is strongest when users follow curated template-driven workflows
- users who outgrow the default structure need external tools or workarounds

After this epic:

- advanced users can shape the app around their training style
- the app supports more flexible planning and deeper review while still keeping the core experience simple for everyone else

## Scope

This epic includes:

- custom exercises
- custom workouts
- personal templates
- deeper analytics
- advanced comparison and review options

## Non-Goals

This epic does not include:

- full coach-client platform features
- bodybuilding-prep grade data science features
- social competition systems
- unlimited customization without guardrails

## Candidate User Stories

1. Custom exercises
2. Custom workout creation
3. Personal workout templates
4. Advanced exercise comparison
5. Advanced performance analytics
6. Optional power-user editing modes

## Sequencing Rationale

- Start with custom exercises and personal templates because they unlock many other advanced workflows.
- Add custom workout creation after the data model and UI patterns for flexibility are stable.
- Add deeper analytics only after the data foundation from previous epics is strong.

## Success Metrics

Primary signals:

- retention among highly active users
- repeated use of advanced customization features
- increased completion of user-defined workouts or templates

Secondary signals:

- custom exercise creation rate
- personal template reuse
- deeper history and analytics engagement

## Risks

- advanced features can complicate the entire product if not isolated carefully
- too much flexibility can damage data consistency and historical comparability
- power-user features can consume large scope without moving broad retention metrics

## Done Criteria

This epic is done when:

- advanced users can customize the product meaningfully without degrading the default flow
- customization and analytics features remain understandable and bounded
- relevant current-state docs are updated
- the value for advanced users is validated against complexity cost

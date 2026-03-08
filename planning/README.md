# Planning Workflow

This folder stores future-oriented product planning artifacts.

It is intentionally separate from `docs/`.

- `docs/` describes the product and system as they exist now.
- `planning/` describes what we intend to build and how we intend to deliver it.

## Artifact Hierarchy

Use this terminology consistently:

- **Feature** — canonical current-state product capability described in `docs/features/`
- **Epic** — large product initiative with a clear business or user outcome
- **User Story** — smallest independently valuable user-facing change within an epic
- **Task** — implementation work item needed to deliver a user story
- **Test Cases** — validation scenarios for the user story
- **MR** — the implementation change that delivers the story
- **Docs** — updates to current-state documentation after the change ships

## Feature Mapping Rules

Use features as the bridge between planning and current-state documentation.

- The product is described as a list of current-state features in `docs/features/`.
- Epics and user stories describe planned changes to those features.
- Every user story must declare one **Primary Feature**.
- A user story may declare **Secondary Features** if it affects additional areas.
- Every user story must declare whether it **Creates New Feature**.
- Every user story must include **Docs To Update** so doc ownership is explicit before implementation starts.

Practical rules:

- If a story changes an existing product capability, map it to that existing feature.
- If a story introduces a genuinely new capability that does not fit an existing feature, give it a planned feature name and mark `Creates New Feature: Yes`.
- The primary feature determines the main feature doc to update after implementation.
- Secondary features only require doc updates if their current behavior changes materially.

## Workflow

Use this default sequence:

1. Epic
2. User Stories
3. Tasks
4. Test Cases
5. MR
6. Docs
7. Review

## Practical Rules

- Planning documents are mainly for epics and user stories.
- Do not create a separate planning document for every engineering task.
- Tasks can live as a checklist inside the user story or inside the MR.
- A user story should be small enough to implement and verify cleanly.
- If a user story contains multiple independent user-visible behaviors, split it.

## User Story Readiness

A user story is ready for implementation when it has:

- primary feature
- secondary features if needed
- creates new feature decision
- problem statement
- user-visible outcome
- scope
- non-goals
- acceptance criteria
- initial task breakdown
- test cases
- docs to update

## Done Criteria

A user story is done when:

- implementation is merged
- relevant tests pass
- current-state docs are updated in `docs/` if behavior changed
- the outcome is reviewed briefly before the next story starts

## After Implementation

Planning specs are not the long-term source of truth after shipping.

After a user story is implemented:

1. update the relevant current-state docs in `docs/`
2. confirm code, tests, and docs reflect the shipped behavior
3. remove the story from active planning
4. archive the story by default if it has future reference value
5. delete the story only if it is clearly low-value and unlikely to be useful later

Default rule:

- current truth lives in `docs/`, code, and tests
- active work lives in `planning/`
- shipped planning specs should not remain mixed with active planning unless they are still being followed up immediately

## Suggested Contents

Recommended planning artifacts in this folder:

- product improvement plans
- epic briefs
- user story specs
- backlog and prioritization notes
- implementation sequencing notes

## Recommended Structure

Use a folder per epic when the epic contains multiple user stories.

Suggested layout:

```text
planning/
	README.md
	product-improvement-plan.md
	epics/
		active-workout-upgrade/
			README.md
			stories/
				01-workout-notes.md
				02-rest-timer.md
				...
```

Practical guidance:

- Create one folder per epic.
- Keep one `README.md` inside the epic folder as the epic brief.
- Create one file per user story when the story is implementation-sized and user-visible.
- Do not create one file per engineering task.
- Keep tasks and test cases inside the user story file unless the story becomes unusually large.
- Keep feature mapping near the top of every user story file.

Avoid using this folder for:

- disposable scratch notes
- current-state product documentation
- low-value temporary exports

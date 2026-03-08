# Epic: Active Workout Upgrade

Improve the active workout experience so logging a real gym session feels faster, more flexible, and more trustworthy.

## Why This Epic Exists

The current product supports starting a workout from a template, editing activities and sets, saving progress, and completing the workout. That is a strong MVP loop, but it still misses several capabilities that regular gym goers expect during a real session.

Current gaps this epic addresses:

- No workout notes
- No rest timer
- No manual activity insertion
- No exercise substitution
- No strong save-state feedback
- No estimated workout duration

These gaps matter because they directly affect whether a user can rely on the app during a workout without switching to other tools or abandoning the workflow.

## Target Audience

Primary: intermediate regulars

Secondary: beginners who need a smoother, more guided workout experience

Tertiary: advanced users who expect more flexibility during training

## Expected Product Diff

Before this epic:

- users follow a template-derived workout with limited flexibility
- users cannot capture context such as notes or rest timing
- the app gives limited support for real-world gym interruptions and adaptations

After this epic:

- users can adapt a workout to real gym conditions
- users can track more session context inside the app
- the active workout flow feels more dependable and complete

## Scope

This epic includes:

- workout notes
- rest timer
- save-state feedback improvements
- exercise substitution
- manual activity insertion
- estimated workout duration

## Non-Goals

This epic does not include:

- full custom workout creation
- template editing
- advanced analytics
- PR detection
- dashboard and streak systems
- social features

## Story Order

1. [Workout Notes](stories/01-workout-notes.md)
2. [Rest Timer](stories/02-rest-timer.md)
3. [Save-State Feedback](stories/03-save-state-feedback.md)
4. [Exercise Substitution](stories/04-exercise-substitution.md)
5. [Manual Activity Insertion](stories/05-manual-activity-insertion.md)
6. [Estimated Duration](stories/06-estimated-duration.md)

## Sequencing Rationale

- Start with notes and save-state feedback because they are highly useful, lower-risk changes.
- Add rest timer next because it improves in-session utility without deep data-model disruption.
- Add substitution and manual activity insertion after the workout-editing model is better understood.
- Add estimated duration once template and workout presentation improvements are ready.

## Success Metrics

Primary signals:

- higher workout completion rate
- lower abandonment of in-progress workouts
- higher repeat usage of the workout edit flow

Secondary signals:

- adoption of notes and rest timer
- reduced friction reports around saving or adapting workouts
- increased percentage of workouts completed without abandoning the app mid-session

## Risks

- adding flexibility can make the workout edit flow more complex
- substitution and manual insertion can damage historical consistency if the data model is too loose
- save-state changes can create new failure modes if UX messaging is misleading

## Done Criteria

This epic is done when:

- all selected user stories are implemented or explicitly deferred
- tests pass for each shipped story
- relevant current-state docs are updated
- the resulting workout flow is reviewed against the original retention goal

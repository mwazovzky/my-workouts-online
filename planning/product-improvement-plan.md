# Product Improvement Plan

Product planning deliverable for improving the app from a solid workout-logging MVP into a product that regular gym goers actively keep using and recommend.

## Objective

Improve retention first, growth second, and advanced-user depth third.

The app already supports the core loop of browsing programs, starting template-based workouts, logging sets, completing workouts, and reviewing history. The next product iteration should make the app more useful during the workout, more motivating between workouts, and easier to adopt for new users.

## Audience Priority

1. Intermediate regulars
2. Beginners
3. Advanced lifters

This order is intentional.

- Intermediate users are the highest-leverage retention segment because they already know what a useful gym app should do.
- Beginners matter for audience growth, but they need guidance layered on top of a strong core experience.
- Advanced users are important, but their needs should be added as optional depth rather than forcing the whole product to become power-user-first.

## Current Product Baseline

The current app supports:

- Program browsing and inspection
- Enrollment state per user
- Starting workouts from templates
- Editing activities and sets during a workout
- Bulk-save workflow for in-progress workouts
- Completing, repeating, deleting, and reviewing workouts
- Authentication, profile management, and locale selection

Key current limitations directly affecting product quality:

- No dashboard or habit view
- No progress tracking, PRs, or workout analytics
- No notes, rest timer, duration tracking, or reflection tools
- No exercise substitution or manual activity insertion during a workout
- No program metadata, filtering, or guided selection
- No unenroll or “next workout” workflow

## Product Principles

1. The product must be genuinely useful during a real gym session.
2. Users must be able to see progress quickly and clearly.
3. New users must understand what to do without reading documentation.
4. Advanced features should add depth without making the default experience noisy.
5. Social features should amplify a strong product, not compensate for a weak one.

## Deliverables

This planning package includes:

1. Current-state product diagnosis
2. Prioritized roadmap themes
3. Ranked feature backlog
4. First-wave epic briefs
5. Success metrics and validation model
6. Execution sequence for future implementation work

## Prioritized Roadmap

### Theme 1: Core Workout Usability

Goal: make the active workout experience clearly better for an everyday gym session.

Why first:

- It hits the most valuable existing user loop.
- It directly affects whether users keep using the app.
- It unlocks better data for future progress features.

Planned capabilities:

- Rest timer
- Workout notes
- Estimated workout duration
- Stronger save and completion feedback
- Manual activity insertion during a workout
- Exercise substitution during a workout
- Optional autosave or draft recovery safeguards

### Theme 2: Progress and Habit Systems

Goal: help users see results, stay consistent, and build attachment to the product.

Why second:

- Users need visible improvement to stay engaged.
- Retention improves when effort feels cumulative.
- These features depend on richer workout data and summaries.

Planned capabilities:

- Dashboard
- Weekly workout consistency view
- Workout streaks
- PR detection
- Volume tracking
- Post-workout summary
- Exercise-level history and trend views

### Theme 3: Beginner Onboarding and Program Discovery

Goal: reduce friction for new users and make program choice less random.

Why third:

- The current app assumes users already know what they want.
- Beginners need guidance before they can form a habit.
- Better discovery also helps retention when users want to switch programs.

Planned capabilities:

- Guided onboarding flow
- Goal-based program recommendation
- Program metadata: difficulty, goal, split frequency, estimated duration
- Search, filter, and sort for programs
- Starter recommendations
- Unenroll or switch-program workflow
- “Next workout” guidance

### Theme 4: Advanced Depth

Goal: keep serious users engaged without overwhelming everyone else.

Why fourth:

- Advanced users care about flexibility and analysis.
- These features are easier to add after the base logging model is stronger.
- They should be optional enhancements, not the default surface.

Planned capabilities:

- Custom exercises
- Custom workout creation
- Editable templates or personal templates
- Deeper analytics
- Optional advanced history and comparison tools

### Theme 5: Motivation and Social Amplifiers

Goal: increase stickiness and word of mouth after the core product is already strong.

Why last:

- Social features do not fix the core workout workflow.
- They make more sense once the product already delivers everyday value.

Planned capabilities:

- Reminders and nudges
- Achievement moments
- Lightweight sharing
- Coach or training partner visibility

## Ranked Feature Backlog

| Rank | Feature | Audience | Impact | Complexity | Why now |
| --- | --- | --- | --- | --- | --- |
| 1 | Rest timer | Intermediate, beginner | High | Medium | Immediate in-session usefulness |
| 2 | Workout notes | Intermediate, advanced | High | Low | Adds context users actually remember and revisit |
| 3 | Manual activity insertion | Intermediate, advanced | High | Medium | Real sessions often diverge from templates |
| 4 | Exercise substitution | Intermediate, advanced | High | Medium | Makes the app usable in crowded or imperfect gyms |
| 5 | Post-workout summary | Intermediate, beginner | High | Medium | Creates closure and prepares for habit features |
| 6 | Dashboard | All | High | Medium | Gives users a reason to return between workouts |
| 7 | PR detection | Intermediate, advanced | High | Medium | Strong motivation and perceived value |
| 8 | Weekly consistency view | All | High | Medium | Supports retention and habit formation |
| 9 | Exercise-level history | Intermediate, advanced | High | Medium | Helps users judge real progress |
| 10 | Program metadata | Beginner, mixed | High | Low | Makes program choice more understandable |
| 11 | Program filters and search | Beginner, mixed | Medium | Medium | Improves discovery once metadata exists |
| 12 | Guided onboarding | Beginner | High | Medium | Helps growth without weakening the core product |
| 13 | Unenroll and switch flow | Beginner, intermediate | Medium | Low | Makes the product feel complete and less sticky in a bad way |
| 14 | Estimated workout duration | All | Medium | Low | Improves planning and commitment |
| 15 | Streaks | All | Medium | Medium | Useful after consistency data exists |
| 16 | Custom exercises | Advanced | Medium | Medium | Valuable depth, but not first-wave material |
| 17 | Custom workouts | Advanced | Medium | High | Strong flexibility, but bigger scope and more product risk |
| 18 | Social sharing | Mixed | Medium | Medium | Better after progress and dashboard features exist |

## First-Wave Epics

These are the recommended first implementation epics.

### Epic 1: Active Workout Upgrade

Goal: make logging a real workout faster, more flexible, and more trustworthy.

Scope:

- Add workout notes
- Add rest timer
- Add manual activity insertion
- Add exercise substitution
- Improve save/completion feedback
- Add estimated duration on workout templates and active workouts

Non-goals:

- Full custom workout builder
- Template editing
- Advanced analytics

User stories:

- As a gym user, I want to swap an exercise when equipment is unavailable.
- As a gym user, I want to add an extra exercise or accessory movement.
- As a gym user, I want to record notes about pain, fatigue, or form.
- As a gym user, I want a rest timer so I do not rely on another app.
- As a gym user, I want clear confirmation that my progress is saved.

Likely affected areas:

- Workout edit page and related components
- Workout save request validation
- Workout service diff-and-save logic
- New workout note fields and possibly duration fields
- New routes or payload extensions for in-workout mutations

Success criteria:

- Users can complete more real workouts without needing external tools.
- Save-related friction decreases.
- Workout completion rate improves.

### Epic 2: Workout Review and Progress Foundation

Goal: make completed workouts informative and turn raw logs into progress signals.

Scope:

- Post-workout summary
- Exercise-level history
- PR detection
- Volume calculations
- History filtering by date, status, and program when available

Non-goals:

- Full coaching insights engine
- Deep social comparisons

User stories:

- As a gym user, I want to see whether I improved on a lift.
- As a gym user, I want to compare today’s workout to the last time I ran it.
- As a gym user, I want to filter past workouts to find relevant sessions quickly.

Likely affected areas:

- Workout show page
- Workout index page
- New query builder scopes
- Aggregation logic or reporting services
- Resource payload changes for history and summaries

Success criteria:

- Users can answer “am I progressing?” inside the app.
- Repeat-workout usage and history revisits increase.

### Epic 3: Dashboard and Consistency Layer

Goal: make the app useful even when the user is not actively logging a workout.

Scope:

- Real dashboard replacing the placeholder
- Weekly consistency view
- Streaks
- Next-workout recommendation or prompt
- Recent achievements or PR moments

Non-goals:

- Public profiles
- Community feed

User stories:

- As a gym user, I want to know if I’m staying consistent this week.
- As a gym user, I want to know what to do next without thinking too much.
- As a gym user, I want a quick reason to open the app between gym sessions.

Likely affected areas:

- Dashboard page
- Program enrollment relationships and schedule awareness
- Workout aggregates and summary queries

Success criteria:

- Weekly active usage increases beyond workout-only sessions.
- Users have a clear next action when opening the app.

## Step-by-Step Execution Plan

1. Finalize product scope for Epic 1.
2. Break Epic 1 into backend, frontend, and product-design stories.
3. Identify schema changes needed for notes, duration, and workout flexibility.
4. Map required route, controller, request, service, resource, and UI changes.
5. Implement and test Epic 1 before touching dashboard or analytics themes.
6. Design the data model for progress metrics while Epic 1 is in development.
7. Implement Epic 2 after the upgraded workout flow is stable.
8. Implement Epic 3 only after the workout-review foundation exists.
9. Start beginner onboarding work after dashboard and metadata requirements are clearer.
10. Re-rank advanced and social features after the first three epics ship.

## Success Metrics

Primary product metrics:

- Weekly active users
- Workouts completed per active user
- Workout completion rate
- Repeat-workout usage
- Week-2 retention

Secondary product metrics:

- History view usage
- Dashboard revisit rate
- Program enrollment-to-first-workout conversion
- Average number of completed workouts per enrolled user

Feature-level success examples:

- Rest timer: increased completion rate for longer workouts
- Workout notes: meaningful adoption among active users
- Dashboard: increased non-workout session opens
- PR detection: increased history and summary engagement

## Decisions and Tradeoffs

- Start with lightweight progress systems before attempting AI coaching or advanced recommendations.
- Add workout flexibility inside the existing workout flow before building a full custom workout builder.
- Prioritize personal utility before social visibility.
- Keep the default experience simple even when advanced features are added.

## What Not to Build First

Avoid starting with:

- Community feed
- Public social layer
- Full trainer or coach system
- Large admin surface redesign
- Deep custom program builder
- Gamification without real progress value

These may become valuable later, but they are not the strongest first move for retention.

## Recommended Next Task

Convert Epic 1 into an implementation-ready specification with:

- user stories
- UI changes
- route and controller plan
- data model changes
- service responsibilities
- test plan
- documentation impact

That is the most practical next step if the goal is to move from strategy to buildable work.

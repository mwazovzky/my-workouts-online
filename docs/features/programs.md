# Programs

Browse workout programs, inspect their templates, and enroll.

## Current Behavior

1. User opens **ProgramIndex** and sees all programs with enrollment state for the current user.
2. User opens **ProgramShow** and sees the selected program plus its workout templates grouped by weekday.
3. User clicks **Enroll** and is enrolled without leaving the page.
4. User opens **WorkoutTemplateShow** to inspect a single template with its activities and sets.

## iOS (`apps/ios`)

- **Programs tab**: all programs with an Enrolled badge and an "Enrolled programs only" switch (client-side filter, like the web); Enroll flips the badge immediately and reverts with an error banner if the API refuses. The list reloads when returning from a detail screen
- **Program detail**: description, Enroll, and the templates grouped by weekday in calendar order, each weekday (`assignment.weekday`, `monday`…`sunday`) labelled from the app's own string catalog; templates without a slot sit under "Not scheduled"
- **Template detail**: read-only activities with equipment, rest time, categories and the planned sets in the two-axis layout (difficulty column, labelled on the device from `exercise.difficulty_unit`, hidden when the unit is `none`; the effort header, reps or seconds, from `exercise.effort_type`); Start Workout creates the workout and opens the editor

## Business Rules

- Programs are global catalog data. All authenticated and verified users see the same list.
- Enrollment is idempotent. Repeating the enroll action has no effect because the relation is synced without duplicates.
- Program responses expose `is_enrolled` per user rather than the full enrolled-users list.
- Templates are attached to programs through a many-to-many relation with a `weekday` attribute (stored lowercase, `monday`…`sunday`); the API returns it as `assignment.weekday` on each template inside a program.
- Templates, template activities, and template sets are read-only for end users.
- Users can start workouts from templates regardless of enrollment status. Enrollment currently affects only the visible state in the program UI.

## Known Limitations

- No unenroll action.
- No search, filter, or sort on the program list.
- No pagination for programs.
- No program categories, media, or difficulty metadata.
- No dashboard summary for enrolled programs or next suggested workout.
- No user-facing CRUD for programs or templates (admins manage them via [Admin Content Management](admin-content-management.md)).

## Surface Area

- Pages: `ProgramIndex`, `ProgramShow`, `WorkoutTemplateShow`
- Route names: `programs.index`, `programs.show`, `programs.enroll`, `workout.templates.show`
- Full reference: [Pages & Routes](../pages-and-routes.md)

## Related

- [Workout Logging](workout-logging.md)
- [Product Overview](../product.md)

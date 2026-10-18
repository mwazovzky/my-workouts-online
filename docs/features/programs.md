# Programs

Browse workout programs, inspect their templates, and enroll.

## Current Behavior

1. User opens **ProgramIndex** and sees all programs with enrollment state for the current user.
2. User opens **ProgramShow** and sees the selected program plus its workout templates grouped by weekday.
3. User clicks **Enroll** and is enrolled without leaving the page (`PUT /api/v1/programs/{programId}/enrollment`). When enrolled, **Unenroll** asks for confirmation ("Your workouts are kept.") and calls `DELETE` on the same URL.
4. User opens **WorkoutTemplateShow** to inspect a single template with its activities and sets; an info button beside an exercise name expands how to perform it.

## iOS (`apps/ios`)

- **Programs tab**: all programs with an Enrolled badge and an "Enrolled programs only" switch (client-side filter, like the web); Enroll flips the badge immediately and reverts with an error banner if the API refuses. The list reloads when returning from a detail screen
- **Program detail**: description, Enroll, and the templates grouped by weekday in calendar order, each weekday (`assignment.weekday`, `monday`…`sunday`) labelled from the app's own string catalog; templates without a slot sit under "Not scheduled"
- **Template detail**: read-only activities with equipment, rest time, categories, an info button that opens the exercise's description in a sheet, and the planned sets in the two-axis layout (difficulty column, labelled on the device from `exercise.difficulty_unit`, hidden when the unit is `none`; the effort header, reps or seconds, from `exercise.effort_type`); Start Workout creates the workout and opens the editor

## Go API (`services/api-go`)

The Go backend serves the program list, program detail, enrollment (`PUT` and `DELETE /programs/{programId}/enrollment`), workout template detail and the exercise picker (`GET /exercises`) with the same responses as the PHP API, in English and Russian; its tests compare them with responses captured from PHP. Enrollment is idempotent both ways, a template scheduled on two weekdays appears twice in assignment order, and a retired exercise still resolves inside a template but is left out of the picker. One small difference: an exercise's categories are listed by category id, where PHP keeps the order they were attached in. Details are in the [Go README](../../services/api-go/README.md#catalog).

## Business Rules

- Programs are global catalog data. All authenticated and verified users see the same list.
- Enrollment is a sub-resource, `/programs/{programId}/enrollment`: `PUT` enrolls, `DELETE` unenrolls, both answer 204 and both are idempotent (enrolling twice leaves one row; unenrolling when not enrolled is still 204). `program_user` has a unique `(user_id, program_id)` index, so concurrent enrolls cannot create a duplicate.
- Unenrolling removes only the caller's enrollment; workouts the user started from the program's templates are their own records and stay.
- Program responses expose `is_enrolled` per user rather than the full enrolled-users list.
- Templates are attached to programs through a many-to-many relation with a `weekday` attribute (stored lowercase, `monday`…`sunday`); the API returns it as `assignment.weekday` on each template inside a program.
- Templates, template activities, and template sets are read-only for end users.
- Users can start workouts from templates regardless of enrollment status. Enrollment drives the program's enrolled state and the dashboard's upcoming schedule and enrolled-programs count.

## Known Limitations

- iOS has no Unenroll control yet; `ProgramRepository.unenroll(programId:)` exists for it.
- No search, filter, or sort on the program list.
- No pagination for programs.
- No program categories, media, or difficulty metadata.
- No next suggested workout beyond the dashboard's upcoming schedule.
- No user-facing CRUD for programs or templates (admins manage them via [Admin Content Management](admin-content-management.md)).

## Surface Area

- Pages: `ProgramIndex`, `ProgramShow`, `WorkoutTemplateShow`
- Route names: `programs.index`, `programs.show`, `programs.enrollment.store`, `programs.enrollment.destroy`, `workout.templates.show`
- Full reference: [Pages & Routes](../pages-and-routes.md)

## Related

- [Workout Logging](workout-logging.md)
- [Product Overview](../product.md)

# Programs

Users browse a curated catalog of workout programs and view their workout templates.

## Current Behavior

1. User opens **ProgramIndex** — sees all programs with name, description, and enrollment badge
2. User clicks a program → **ProgramShow** — sees program details and workout templates grouped by weekday (deferred load)
3. User clicks a template → **WorkoutTemplateShow** — sees template name, description, activities with exercises and sets

## Business Rules

- Programs are global catalog data — all authenticated users see the same list
- Program list includes an `is_enrolled` flag per user (derived from `users_count`)
- Templates are linked to programs via M:N pivot with a `weekday` attribute
- Templates, activities, and sets in templates are read-only for users

## Known Limitations

- No search, filter, or sort on program list
- No program categories or difficulty levels
- No pagination — all programs load at once
- Templates show on ProgramShow but without exercise detail (activities loaded without equipment/categories)
- No program images or media
- No program CRUD — all data is seeded
- Dashboard is empty — no enrolled-programs summary or "next workout" suggestion

## Pages & Routes

| Page | Route | Name |
|---|---|---|
| ProgramIndex | `GET /programs` | `programs.index` |
| ProgramShow | `GET /programs/{id}` | `programs.show` |
| WorkoutTemplateShow | `GET /workout-templates/{id}` | `workout.templates.show` |

## Related

- [Enrollment](enrollment.md) — enrolling in a program
- [Workout Logging](workout-logging.md) — starting a workout from a template

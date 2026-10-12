# 0011. The Dashboard Is a Read Model over the Modules' api Packages

- Status: accepted
- Date: 2026-10-12
- Story: [Go epic story 09](../../../../planning/epics/go-backend/stories/09-dashboard.md)

## Context

`GET /dashboard` is the only operation that reads across modules: the enrolled programs and their weekday assignments (catalog) and the user's workouts and completion counts (training). The dependency rules (ADR 0001) keep modules from importing each other's internals. The epic planned a `dashboard` module as the read side, allowed to query any table directly when that is cheaper than going through the modules.

PHP's `DashboardController` computes each assignment's next date as `(target − today.dayOfWeek + 7) % 7` days ahead, in UTC, with `today` counting; builds the ids `"{program_id}-{workout_template_id}-{date}"` the clients key on; sorts by date, stably; counts completions by `updated_at` over an exact seven-day span; and lists the 5 most recently updated workouts and the most recent unfinished one, each with its activities count and its template's header.

## Decision

`internal/dashboard` owns no tables, writes nothing, and holds no SQL. Its use case composes:

- `catalogapi.EnrolledSchedule` (assignments, by program and then in assignment order, localized), `catalogapi.EnrolledPrograms` (the count, programs without assignments included) and `catalogapi.TemplateHeaders` (the workouts' templates);
- `trainingapi.InProgress`, `RecentWorkouts(5)` and `Counts(since now − 7 days)`.

Every one of those is one query, so composing them costs five or six queries per request, independent of the data's size, and a direct cross-table query would save nothing worth the coupling. The rule stays as planned for when that changes: the dashboard may read any table, through its own `sql/dashboard` queries, but never writes.

The date arithmetic lives in the dashboard (`app/schedule.go`): `Today` is the UTC midnight of the clock's instant, so a time zone or DST change never moves the day; `NextDate` is PHP's formula; `Upcoming` builds the ids and sorts stably by date, so same-day items keep their assignment order. The catalog's domain no longer carries a `Weekday.Next`, since only the dashboard dates anything.

`in_progress_workout` is left out, not null, when there is none, as the contract says. Workouts appear as in the history list: no activities, their count, their template's header while the template exists.

## Consequences

- Good: the dashboard depends on two small interfaces and is tested with their fakes; the HTTP tests and a run against the PHP API on the real catalog give the same schedule.
- Good: the 7 × 7 weekday table, a DST night and the window's edges (6 days 23 hours in, 7 days 1 hour out) are tested.
- Bad: two catalog calls and three training calls per request instead of one hand-written query. Accepted at this size.

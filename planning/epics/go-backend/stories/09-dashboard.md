# User Story: Dashboard

Serve `GET /dashboard` from a read model that composes the enrolled schedule, the in-progress workout, recent history and summary counts, with the PHP date arithmetic ported exactly.

## Feature Mapping

- Primary Feature: Workout Logging
- Secondary Features: Programs (enrolled schedule)
- Creates New Feature: No

## Problem Statement

The dashboard is the only endpoint that reads across modules: enrolled programs and weekday assignments from the catalog, workouts from training. In PHP it lives in `app/Http/Controllers/Api/DashboardController.php` and computes the next occurrence of every assignment with `(target − today.dow + 7) % 7`, builds composite ids, and counts completions by `updated_at`. The Go modules so far expose nothing for this, and the dependency rules forbid `training` from importing `catalog` adapters. A dedicated read model is the approved answer; it may query any table but never writes.

## User-Visible Outcome

None. The web SPA and iOS app render the same dashboard from either backend; the Go service answers the same contract.

## Scope

- `internal/catalog/catalogapi.EnrolledSchedule(ctx, userID)` returns `[]Assignment{ProgramID, ProgramName(locale), TemplateID, TemplateName(locale), Weekday}` ordered by `program_workout_template.id` (story 6 declared it; this story implements and tests it).
- `internal/training/trainingapi`: `InProgress(ctx, userID)` (latest `updated_at`), `RecentWorkouts(ctx, userID, 5)`, `Counts(ctx, userID, since time.Time)` returning completed totals and completed-since.
- `internal/dashboard/app.GetDashboard(ctx, Input{UserID, Locale})`:
  - `upcoming_schedule`: for each assignment, `days = (weekday − today.Weekday() + 7) % 7` in UTC, `scheduled_for = today + days` as `date`, `scheduled_at` as the UTC midnight `date-time`, `id = "{program_id}-{workout_template_id}-{YYYY-MM-DD}"`, `weekday` lowercase, `weekday_label` from the i18n bundle, sorted by `scheduled_at` then id.
  - `in_progress_workout`: the key is omitted when there is none (never `null`).
  - `recent_workouts`: at most 5, newest updated first, same `Workout` shape as story 8 including `ETag`-less embedding.
  - `summary`: `enrolled_programs_count`, `completed_workouts_count`, `completed_last_7_days_count` (by `updated_at >= now − 7d`), `upcoming_workouts_count` (length of `upcoming_schedule`).
- `internal/dashboard/adapters/http`: the `getDashboard` operation; `Content-Language` and `Vary` set by the locale middleware.
- Time comes from the injected `kernel/clock.Clock`; tests fix "today" on each weekday and on a DST boundary to prove UTC arithmetic.
- One sqlc query under `sql/dashboard/` is allowed to join `program_user`, `program_workout_template`, `workout_templates`, `programs` for the schedule; record in ADR 0011 that `dashboard` is the CQRS read side: cross-table reads, no writes, no `*api` imports needed when a query is cheaper.
- Append `getDashboard` to `services/api-go/contract-scope.txt`.

## Non-Goals

- caching or precomputation of the schedule
- changing the `id` format or the weekday arithmetic (the clients key on both)
- a `Dashboard` write path of any kind

## Acceptance Criteria

- for a user enrolled in two programs sharing a template on `monday`, the schedule contains two items with distinct `program_id` and the same `scheduled_for`, matching `tests/Feature/Api/DashboardTest.php`
- with the clock fixed to a Wednesday, a `monday` assignment yields `scheduled_for` five days ahead; a `wednesday` assignment yields today
- `in_progress_workout` is absent from the JSON (not `null`) when none exists; present with the newest in-progress workout otherwise
- `completed_last_7_days_count` counts a workout completed 6 days 23 hours ago and excludes one at 7 days 1 hour
- `recent_workouts` never exceeds 5 and includes completed and in-progress workouts
- Schemathesis scoped to `getDashboard` is green; response validation in the HTTP test passes with both `in_progress_workout` present and absent
- `make check` green

## Initial Tasks

- [ ] implement and test `catalogapi.EnrolledSchedule`
- [ ] `trainingapi` read functions and sqlc queries
- [ ] `internal/dashboard/app` use case with table-driven date tests over every weekday
- [ ] HTTP adapter and golden files (with and without an in-progress workout)
- [ ] ADR 0011 (read model rules)
- [ ] `contract-scope.txt`, docs

## Test Cases

- unit: weekday arithmetic for all 7 × 7 combinations of today × target, in UTC; composite id formatting; sort order
- repo: schedule query returns assignments in pivot id order and localizes names with English fallback
- HTTP: port of `tests/Feature/Api/DashboardApiTest.php`; `Accept-Language: ru` yields Russian program and template names, while `weekday` stays the lowercase enum (no label)
- contract: Schemathesis scoped to `getDashboard`

## Docs To Update

- `docs/features/workout-logging.md` (dashboard section: Go read model)
- `docs/architecture.md` (dashboard module, read-side rule)
- `services/api-go/docs/adr/0011-dashboard-read-model.md`

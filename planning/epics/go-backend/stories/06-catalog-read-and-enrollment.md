# User Story: Catalog Read and Enrollment

Serve programs, program detail, enrollment, workout-template detail and the exercise picker from the `catalog` module, and expose the read ports that `training` and `dashboard` will build on.

## Feature Mapping

- Primary Feature: Programs
- Secondary Features: Workout Templates, Exercises
- Creates New Feature: No

## Problem Statement

The program flow is the first screen a verified user sees in both clients, and it is pure catalog reading plus one idempotent write (enrollment). In PHP the behaviour is spread across `app/Http/Controllers/Api/{Program,WorkoutTemplate,Exercise}Controller.php`, the `HasTranslations` trait and the API resources: localized names with English fallback, retired exercises still resolving inside templates, a template allowed on several weekdays, exercises sorted by localized name with retired ones hidden. The same module must publish a small `catalogapi` surface so `training` can copy a template and `dashboard` can compute the schedule without importing catalog internals.

## User-Visible Outcome

None. The web SPA and iOS app behave identically; against the Go service a user can browse programs, open a program and a template, enroll, and pick exercises.

## Scope

- `internal/catalog/domain`: `Equipment`, `Category`, `Exercise` (with `RetiredAt`), `WorkoutTemplate` with `TemplateActivity` and `TemplateSet`, `Program` with `Assignment{TemplateID, Weekday}`, `LocalizedText{Source string; Other map[Locale]string}` with `Resolve(locale)`, enums `EffortType`, `DifficultyUnit`, `Weekday` (lowercase values, `Weekday.Next(from time.Time)` for the dashboard).
- `sql/catalog`: queries reading `COALESCE(translations #>> ARRAY[$locale,'name'], name)` and the same for `description`; `program_workout_template` ordered by its id so duplicate assignments keep PHP's order; exercise listing with equipment and categories in one query per relation (no N+1), `deleted_at IS NULL` for the picker, no filter for template activities.
- Use cases: `ListPrograms(userID, locale)` with `is_enrolled`; `GetProgram` with `workout_templates`, each carrying `assignment{weekday}` (the hardened contract's replacement for `pivot`; lowercase enum, no label); `Enroll` (`PUT /programs/{programId}/enrollment`, 204, idempotent through the unique constraint) and `Unenroll` (`DELETE`, 204, idempotent); `GetWorkoutTemplate` with activities ordered by `"order"`, sets, and a nested `exercise` snapshot (`id`, `name`, `equipment_name`, `effort_type`, `difficulty_unit`, `category_names`, `rest_time_seconds`) that resolves retired exercises; `ListExercises` sorted by the localized name with a case-insensitive collation, `categories`, `category_ids`, `equipment_name`, `difficulty_unit` (no server-rendered labels; clients map the enums).
- `internal/catalog/catalogapi`: `ExerciseLookup(ctx, ids) (map[int64]ExerciseInfo, error)` including retired exercises, `TemplateSnapshot(ctx, id, locale) (Snapshot, error)` (localized name, activities with exercise ids and sets), `EnrolledSchedule(ctx, userID) ([]ScheduledTemplate, error)` (program id and name, template id and name, weekday) — plain DTOs, no domain types.
- Policy: all five operations require `verified`; 404 Problem for unknown ids.
- Scope file gains `listPrograms getProgram enrollProgram unenrollProgram getWorkoutTemplate listExercises`.

## Non-Goals

- admin catalog CRUD (story 10)
- catalog sync and seed data (story 7); tests build their own fixtures through the repositories
- changing the ordering or shape of any response beyond what the hardened contract already defines

## Acceptance Criteria

- with the same catalog rows inserted into both databases, `GET /programs`, `GET /programs/{id}`, `GET /workout-templates/{id}` and `GET /exercises` return byte-equal JSON from PHP and Go for `en` and `ru` (golden files captured from PHP)
- a template assigned to Monday and Thursday appears twice in program detail in pivot order
- a retired exercise shows its name inside a template and is absent from `/exercises`
- `PUT /programs/{id}/enrollment` twice is 204 both times and creates one row; `DELETE` twice is 204 both times
- `contract-go` is green for the widened scope
- `tests/Feature/Api/{Program,WorkoutTemplate,Exercise}ApiTest.php`, `tests/Feature/Program/ProgramEnrollTest.php` and `tests/Feature/TranslatedResourceTest.php` each have a Go counterpart per test method

## Initial Tasks

- [ ] domain types and `LocalizedText` with unit tests
- [ ] sqlc queries and repositories; eager loading verified by query count in a test
- [ ] use cases with fakes; handlers; policy entries
- [ ] `catalogapi` package and its fakes for other modules' tests
- [ ] golden files from PHP; scope file

## Test Cases

- `LocalizedText.Resolve`: present, blank (falls back), unknown locale
- program list enrollment flag per user; program detail for a program with no templates
- exercise sort order with mixed case and Cyrillic names under `ru`
- template detail includes sets with `difficulty_value` null for `none` equipment
- `catalogapi.TemplateSnapshot` for a template with a retired exercise
- 404 for unknown program, template; 403 `email_unverified` for an unverified user

## Docs To Update

- `docs/features/programs.md` (Go implementation note; enrollment resource unchanged from hardening)
- `docs/architecture.md` (catalog module, `catalogapi` surface)

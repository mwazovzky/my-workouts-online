# User Story: Naming Consistency

Make every path parameter, enum value and field name follow one convention and stop leaking Eloquent names into responses.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Programs, Workout Logging, Admin Content Management
- Creates New Feature: No

## Problem Statement

Path parameters are named after Laravel route bindings — `{program}`, `{workout}`, `{id}`, `{equipment}`, `{category}`, `{exercise}`, `{workout_template}` — and generators turn them into argument names (`path: .init(program: 3)`). `Weekday` is `Monday` … `Sunday` while every other enum is lowercase snake. `WorkoutTemplate.pivot` is Eloquent's pivot accessor. `created_at` and `updated_at` are nullable but never null. `User` mixes `is_admin` with `email_verified`. `Activity` carries six `exercise_*` fields that are a flattened snapshot of one object. Four `*_label` fields ship server-rendered UI strings in a data API, against the client-owned i18n decision of the API-first epic.

## User-Visible Outcome

None, unless the `*_label` decision changes how a label is produced; the rendered text stays the same.

## Scope

- Contract: path parameters `{programId}`, `{workoutId}`, `{workoutTemplateId}`, `{equipmentId}`, `{categoryId}`, `{exerciseId}` (components `ProgramId`, `WorkoutId`, … renamed to match); `Weekday` enum `monday` … `sunday`; `WorkoutTemplate.pivot` → `assignment` `{weekday}`; `Workout.created_at`/`updated_at` required `date-time`; `User.email_verified` → `email_verified_at` (`date-time` or null); `Activity.exercise_*` → `exercise` object `{id, name, equipment_name, effort_type, difficulty_unit, category_names, rest_time_seconds}` (the object is optional, omitted when the relation is not loaded, so no nullable `$ref`); `*_label` fields: decide and record in the epic README — recommendation is to drop `status_label`, `weekday_label`, `effort_label`, `difficulty_label` and `exercise_effort_label`/`exercise_difficulty_label`, with both clients mapping enums through their own catalogs (web already has `useTranslation`, iOS has `Localizable.xcstrings`).
- PHP: `routes/api.php` parameter names and the `[0-9]+` patterns; `app/Enums/Weekday.php` backing values and `carbonDayOfWeek()`; a migration rewriting `program_workout_template.weekday` in place (`UPDATE … SET weekday = LOWER(weekday)`) with a down migration; `database/catalog/programs.php` and the `CatalogValidator` weekday rule; `tests/Fixtures/catalog/`; `WorkoutTemplateResource` (`assignment`), `UserResource` (`email_verified_at`), `WorkoutResource` (non-null timestamps), `ActivityResource` (nested `exercise`), the label fields per the decision.
- Web: `apps/web/src/pages/ProgramShow.vue`, `WorkoutTemplateShow.vue`, `WorkoutEdit.vue`, `WorkoutShow.vue`, `Dashboard.vue`, `Admin/Program*.vue` read the new shapes; `stores/auth.js` reads `email_verified_at`; label rendering moves to `useTranslation` where labels are dropped.
- iOS: `Mapping/APIMapping.swift`, `Domain/*.swift`, every repository's `path:` arguments, `Enums.swift` weekday raw values, fixtures; labels from `Localizable.xcstrings` where dropped.
- `npm run api:types` regenerated.

## Non-Goals

- renaming `workout_template_id`, `equipment_id` and other `*_id` foreign keys (already consistent)
- changing `client`, `device_name` or any auth body field
- opaque or UUID identifiers

## Acceptance Criteria

- Redocly lint green; Spectral green with the naming rules enabled
- `scripts/contract-test.sh` green on the dev stack
- PHP suite green; web `npm test` and `npm run build` green; iOS `swift test` in `WorkoutsCore` and `xcodebuild test` green
- `npm run api:types` regenerated and the generated Swift client has `path: .init(workoutId:)` style arguments
- `GET /programs/{id}` lists templates with `assignment.weekday: "monday"` and no `pivot`
- `catalog:sync --dry-run` reports `unchanged` for every program after the migration
- `GET /me` carries `email_verified_at` and no `email_verified`
- the `*_label` decision is written into the epic README's Decisions table before the PR merges

## Initial Tasks

- [ ] contract renames; decide labels and record the decision
- [ ] routes, enum, migration, catalog data, validator, fixtures
- [ ] resources
- [ ] web pages and store
- [ ] iOS mapping, domain, repositories, enums, fixtures, string catalog entries
- [ ] regenerate web types; docs

## Test Cases

- `tests/Feature/Api/RouteParameterConstraintsTest.php`: renamed parameters still enforce `[0-9]+`
- `tests/Feature/Api/ProgramApiTest.php`, `WorkoutTemplateApiTest.php`, `WorkoutApiTest.php`, `MeApiTest.php`, `DashboardApiTest.php`: new field names; `tests/Unit/Resources/*`
- `tests/Feature/Catalog/CatalogSyncTest.php` and `tests/Unit/Catalog/ValidatorTest.php`: lowercase weekdays; `tests/Feature/Migrations/` test for the weekday rewrite
- web page tests touching schedule, templates and the editor
- iOS `APIMappingTests.swift`, `ProgramViewModelTests.swift`, `DashboardViewModelTests.swift`, `WorkoutEditorModelTests.swift`

## Docs To Update

- `docs/architecture.md` (naming conventions: parameters, enums, snapshots)
- `docs/features/programs.md`, `docs/features/workout-logging.md`, `docs/features/admin-content-management.md`, `docs/features/auth-and-profile.md` (field renames)
- `docs/code-style.md` (API resources: no Eloquent names, no server-rendered labels if dropped)

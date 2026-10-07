# User Story: Admin Catalog CRUD

Implement the 25 `admin*` operations for equipment, categories, exercises, workout templates and programs, with translation maps, reference guards and transactional replacement of nested data.

## Feature Mapping

- Primary Feature: Admin Content Management
- Secondary Features: Programs, Workout Logging (templates feed both)
- Creates New Feature: No

## Problem Statement

Stories 6 and 7 gave the catalog module a read side and a file-driven sync; the admin web pages still need the write side over HTTP. The PHP implementation spreads the rules across `app/Services/Admin/{Equipment,Category,Exercise,WorkoutTemplate,Program}Service.php`, the `Admin\*StoreRequest` form requests with `HasTranslationRules`, and guard checks in the controllers: an English name is mandatory, blank non-English values delete the translation, equipment cannot be deleted while any exercise (including retired ones) references it, a template cannot be deleted while a program assigns it, exercise deletion is a soft retire, template and program updates replace their nested rows wholesale. Lists are ordered by id and carry counts. All of this must be reproduced behind the same contract, with the admin policy derived from the `Admin` tag.

## User-Visible Outcome

None for end users. Admins using the web SPA against the Go backend see the same catalog pages, validation messages and refusals.

## Scope

- `internal/catalog/app/admin`: one use-case file per resource with `List`, `Get`, `Create`, `Update`, `Delete`; shared `TranslationsInput` parsing `translations: {en: {name, description}, ru: {...}}` into `domain.LocalizedText` (English into the source columns, other locales into the `translations` jsonb; blank or null non-source values remove that locale's entry; `en.name` missing or blank → 422 `required` at `/translations/en/name`; lengths → `too_long` at the exact pointer).
- Validation ported from `app/Http/Requests/Admin/*`: `difficulty_unit`/`effort_type` enums (`invalid_value`), `rest_time_seconds` 0–3600 nullable, `category_ids` must exist (`not_found` at `/category_ids/i`), template `activities` min 1 with `exercise_id` existing and sets min 1 with `order` 1–1000, `effort_value` 0–100000, `difficulty_value` 0–10000 plus the heart-rate zone rule through `ExerciseLookup`; program `assignments` with existing `workout_template_id` and a lowercase `weekday`.
- Write semantics: `ExerciseService.Update` syncs `category_exercise`; `WorkoutTemplateService.Create/Update` deletes and recreates `template_activities` + `template_sets` in one transaction; `ProgramService.Create/Update` deletes and re-inserts `program_workout_template` rows (the same template may appear on several weekdays); all through `txn.Transactor`.
- Guards: equipment delete → 409 `in_use` when `exercises` (including `deleted_at IS NOT NULL`) reference it; template delete → 409 `in_use` when assigned; exercise delete sets `deleted_at` and keeps translations; category and program deletes cascade their pivots (PHP behaviour kept; recorded in ADR 0012 with the sync-time blockers as the stricter counterpart).
- Responses: `AdminCategory`, `Equipment`, `AdminExercise`, `AdminWorkoutTemplate`, `AdminProgram` with `translations: {field: {en, ru}}` maps assembled from column + jsonb (no fallback, `null` for a missing locale), `exercises_count`, `activities_count`, `templates_count`, program `assignments[]` with `workout_template_name` and `weekday_label`; lists ordered by id, unpaginated as the contract says.
- Policy: `platform/httpapi/middleware/policy` already maps the `Admin` tag to `Principal.IsAdmin`; this story adds the 403 `forbidden` tests per operation and the 401 for anonymous callers.
- Append every `admin*` operation id to `services/api-go/contract-scope.txt`.

## Non-Goals

- pagination or filtering on admin lists (contract has none)
- changing guard behaviour for category or program deletes
- admin pages in `apps/web` (unchanged)
- catalog sync (story 7) beyond reusing the same repositories

## Acceptance Criteria

- Schemathesis scoped to all `admin*` operations is green, including the 409 and 422 paths
- creating equipment with `translations: {en: {name: ''}}` returns 422 with pointer `/translations/en/name` and code `required`
- updating a category with `ru: {name: ''}` removes the Russian entry; a subsequent `GET` with `Accept-Language: ru` on the public endpoint falls back to English
- deleting equipment referenced only by a retired exercise returns 409 `in_use`
- deleting an exercise returns 204, hides it from `listExercises`, and `getWorkout` on a workout using it still shows the exercise snapshot
- updating a template with a different activity set leaves exactly the new activities and sets, in one transaction (a failing set validation rolls everything back)
- a program update assigning the same template to `monday` and `thursday` stores two rows and returns both assignments in pivot id order
- non-admin verified users receive 403 `forbidden` on every admin operation; anonymous callers 401
- `make check` green

## Initial Tasks

- [ ] translation input parsing and pointer-accurate validation in `internal/catalog/app/admin`
- [ ] sqlc queries for counts, guards, nested replace; repositories
- [ ] use cases per resource with fakes; HTTP adapter for the 25 operations
- [ ] golden files per admin response shape
- [ ] HTTP tests ported from `tests/Feature/Admin/*Test.php` and `tests/Unit/Requests/HasTranslationRulesTest.php`
- [ ] ADR 0012 (delete guards)
- [ ] `contract-scope.txt`, docs

## Test Cases

- unit: `TranslationsInput` → `LocalizedText` (required English, blank removal, length limits, unknown locale rejected)
- repo: equipment guard counts retired exercises; template replace is atomic; program assignments allow duplicates across weekdays
- HTTP: every case in `tests/Feature/Admin/{Equipment,Category,Exercise,WorkoutTemplate,Program}Test.php`, plus 401/403 per operation
- contract: Schemathesis scoped to `admin*`

## Docs To Update

- `docs/features/admin-content-management.md` (Go implementation; guard table)
- `docs/architecture.md` (catalog module write side)
- `services/api-go/docs/adr/0012-admin-delete-guards.md`

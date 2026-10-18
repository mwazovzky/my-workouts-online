# 0012. Admin Catalog Writes: Delete Guards and Departures from PHP

- Status: accepted
- Date: 2026-10-13
- Story: [Go epic story 10](../../../../planning/epics/go-backend/stories/10-admin-catalog-crud.md)

## Context

The 25 admin operations let an admin create, edit and delete equipment, categories, exercises, workout templates and programs. PHP spreads the rules over its `Admin*` form requests (with `HasTranslationRules`), services and controllers. Two kinds of rules matter most:

- **Deletes.** Equipment is refused (409 `in_use`) while any exercise uses it, retired exercises included, since their history still names it. A template is refused while a program schedules it. An exercise is never deleted but retired (`deleted_at`), so templates and logged workouts keep resolving it. Categories and programs are deleted outright, their links with them.
- **Translations.** English is required and lives in the row's columns; other locales live beside it; a blank value removes that locale's translation so reads fall back to English. Laravel trims every input string first.

Catalog sync (ADR 0009) is stricter on removal: it never deletes a category that exercises belong to or a program users are enrolled in; it reports those as warnings and skips them.

## Decision

The Go admin (`internal/catalog/app/admin`) keeps PHP's guards exactly: equipment and templates are guarded, exercises are retired, and categories and programs are deleted with their links (exercises leave the category; enrollments and assignments go with the program). The admin is an explicit, per-record action, so the softer PHP rules for categories and programs stay; the sync, which removes records in bulk on deploy, keeps its stricter blockers. Records created here have no slug, so catalog sync never touches them.

Responses show what each PHP endpoint shows, verified field by field against PHP on the real catalog: a list carries its counts (`exercises_count` of active exercises, `activities_count`, `templates_count` counting a template scheduled twice twice) and no nested rows; a single record its nested rows and no counts; an exercise's create and update responses leave out `equipment_name` and `difficulty_unit`, which PHP does not reload. Translation maps are `{en, ru}` with `null` for a missing value, and retired exercises are hidden from the admin list and are 404 on show, update and delete.

Departures, none of which the admin web pages can trigger:

- **A field left out of a sent locale is blank.** PHP leaves an omitted field untouched and clears a `null` one; the generated request types cannot tell the two apart, so Go clears both. A locale left out entirely is still untouched. The admin forms always send every field.
- **Template payloads are checked like workout payloads (ADR 0010).** A retired exercise is accepted only where the template already uses it, and a repeated activity order, or set order within an activity, is a 422 `not_unique` instead of a server error from the unique keys. PHP adopted the same checks in [story 15](../../../../planning/epics/go-backend/stories/15-php-parity-fixes.md), so this is no longer a departure.
- **`translations: {en: {}}`** reports the missing name only; PHP also reports the empty `en` object.

## Consequences

- Good: the admin web pages work unchanged against Go: a scripted create, edit, validate and delete flow run on both backends gave the same responses apart from the last departure above.
- Good: no payload reaches a database constraint as a 500.
- Bad: two delete policies exist, the admin's (PHP's) and the sync's (stricter). Both are documented; aligning them would change PHP too.

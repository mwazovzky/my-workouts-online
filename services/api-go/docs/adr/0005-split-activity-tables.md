# 0005. Split Template and Workout Activities into Their Own Tables

- Status: accepted
- Date: 2026-10-11
- Story: [Go epic story 02](../../../../planning/epics/go-backend/stories/02-schema-migrations-sqlc-and-test-harness.md)

## Context

A workout template and a logged workout have the same structure: ordered activities, each an exercise with ordered sets. The Laravel API stores both in one polymorphic pair of tables. `activities (workout_type, workout_id, exercise_id, order)` points at either `workout_templates` or `workouts` through a morph map, and `sets (activity_id, order, effort_value, difficulty_value, is_completed)` hangs off it, with `is_completed` meaningless for template sets.

Consequences in PHP:

- `activities.workout_id` has no foreign key, so deleting a template or a workout relies on application code to remove its activities.
- Every query filters on `workout_type`, and sqlc cannot type a column whose meaning depends on another column.
- Creating a workout from a template copies rows within the same tables, so template and workout data share unique constraints and indexes.

In the Go service, templates belong to the `catalog` module and workouts to `training` (ADR 0001). A use case writes only its own module's tables, and no transaction spans two modules. A shared `activities` table would belong to both modules.

## Considered Options

1. Port the polymorphic `activities` and `sets` tables.
2. Separate tables per owner: `template_activities` and `template_sets` in catalog, `workout_activities` and `workout_sets` in training, with the same column shapes.
3. Store a template's or workout's activities and sets as a `jsonb` document on the parent row.

## Decision

Option 2. `template_activities.template_id` references `workout_templates` and `workout_activities.workout_id` references `workouts`, both `ON DELETE CASCADE`, and each sets table cascades from its activities table. Positions are unique per parent: `(template_id, "order")`, `(workout_id, "order")` and `(activity_id, "order")`. Only `workout_sets` has `is_completed`. `difficulty_value` is `numeric(6,1)` in both, as in PHP.

Creating a workout does not read the template tables from `training`. The training use case asks `catalogapi` for a `TemplateSnapshot` (plain data: localized name, activities, sets) and writes its own tables from it (story 8). After that, a workout never looks at its template again, and `workouts.template_id` is `ON DELETE SET NULL` so history outlives the template.

Departures from the PHP foreign keys, chosen with the split:

- `template_activities.exercise_id` and `workout_activities.exercise_id` are `ON DELETE RESTRICT` (PHP cascades). Exercises are retired with `deleted_at`, never hard-deleted by the API. A stray hard delete should fail, not silently strip exercises from templates and history.
- `program_workout_template.template_id` is `ON DELETE RESTRICT` (PHP cascades and checks in application code), so the database itself enforces the 409 `in_use` guard on deleting a scheduled template.
- `program_user` and `role_user` have composite primary keys instead of a surrogate id, which gives the unique constraints PHP lacked (PHP added the enrollment one later, in the hardening epic).

Option 3 was rejected because sets are edited individually during a workout and replaced as a list by `PUT …/activities`. Both are plain row operations with constraints the database can check. A document column would move those checks into Go and make the dashboard's per-set queries awkward.

Implemented in `migrations/00002_catalog.sql` and `migrations/00003_training.sql`.

## Consequences

- Good: every row has a real parent, deletes cascade by declaration, and sqlc generates distinct types for template and workout rows.
- Good: each module owns its tables outright, so the module boundary and the transaction boundary coincide.
- Bad: two pairs of near-identical tables and queries. Accepted; they already differ (`is_completed`) and will drift further (workout sets are edited, template sets are not).
- Bad: replacing a workout's activities in place must avoid transient `(workout_id, "order")` collisions, which PHP solves with negative temporary orders. Story 8 chooses between that, delete-and-reinsert, and a deferrable constraint.
- Follow-up: story 6 exports `TemplateSnapshot` from `catalogapi`; story 8 copies it; story 10's admin template update replaces `template_activities` wholesale.

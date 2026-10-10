# User Story: Schema, Migrations, sqlc and Test Harness

Give the Go service its own Postgres schema, owned by goose migrations and compiled into typed queries by sqlc, plus a database test harness every later story reuses.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

The Laravel schema in `services/api-php/database/migrations/*` carries two patterns that do not translate well to Postgres with typed SQL: the polymorphic `activities` table (`workout_type`/`workout_id` over both workouts and templates, so no real foreign key) and the EAV `translations` table (`translatable_type`/`translatable_id`/`locale`/`field`/`value`). It also leaves `program_user` and `role_user` without unique constraints. The Go service owns a fresh schema (epic decision 2), so this is the one chance to fix those shapes before any query depends on them. Every later story also needs a way to run real SQL in tests without sharing a database between parallel test packages.

## User-Visible Outcome

None. The web SPA and iOS app behave identically; the Go service gains a database it does not yet serve from.

## Scope

- `migrations/00001_identity.sql`: `users` (id, name, email unique, email_verified_at, password_hash, locale default `en`, theme_preference default `system`, timestamps), `roles`, `role_user` with a unique `(user_id, role_id)`, `refresh_tokens` (family_id uuid indexed, token_hash unique, device_name, authenticated_at, expires_at, revoked_at, replaced_by_id self-reference `ON DELETE SET NULL`), `password_reset_tokens` (email primary key, token_hash, created_at). Seed rows for the `Admin` and `User` roles.
- `migrations/00002_catalog.sql`: `equipment` (slug unique nullable, name, difficulty_unit, `translations jsonb NOT NULL DEFAULT '{}'`), `categories`, `exercises` (slug, name, description, equipment_id `ON DELETE RESTRICT`, effort_type, rest_time_seconds, `previous_names text[]`, `deleted_at`, translations), `category_exercise`, `workout_templates`, `template_activities` (template_id cascade, exercise_id, `"order"`, unique `(template_id, "order")`), `template_sets` (activity_id cascade, `"order"`, effort_value, difficulty_value `numeric(6,1)`, unique `(activity_id, "order")`), `programs`, `program_workout_template` (weekday, no unique: the same template may repeat on several weekdays by design), `program_user` with a unique `(program_id, user_id)`.
- `migrations/00003_training.sql`: `workouts` (user_id cascade, template_id `ON DELETE SET NULL`, name snapshot, status default `in_progress`, `version integer NOT NULL DEFAULT 1` (the `ETag` counter, bumped by every replace and by complete), timestamps), `workout_activities`, `workout_sets` with the same column shapes and `(owner_id, "order")` uniques as the template tables, plus `is_completed`.
- `migrations/00004_river.sql`: River's schema, applied through the same runner so a deploy has one `migrate` step.
- `sqlc.yaml`: one `sql` block per module, `sql/identity`, `sql/catalog`, `sql/training`, `sql/dashboard`, each generating into that module's `adapters/postgres/sqlcgen` package; pgx/v5 engine, `query_parameter_limit: 2`, `emit_methods_with_db_argument: false`. First queries: the ones the harness smoke test needs (insert and select a user).
- `internal/platform/postgres`: `pgxpool` construction from `DATABASE_URL`, the goose runner over an `embed.FS`, a `Transactor` implementing `kernel/txn` (`InTx(ctx, func(ctx) error)`) that stores the `pgx.Tx` in the context, `FromContext(ctx) DBTX`, and the pgx error mapper (`23505` → `apperr.Conflict` with the constraint name, `23503` → `apperr.Conflict`, `pgx.ErrNoRows` → `apperr.NotFound`). Readiness registers a `database` component that runs `SELECT 1`, not just a connection check, unlike `/health/ready` in PHP.
- `internal/platform/testdb`: `testcontainers-go/modules/postgres` started once per `go test` package, migrations applied to a template database, `CREATE DATABASE … TEMPLATE` per test so `t.Parallel()` is safe; `TEST_DATABASE_URL` overrides the container with the compose Postgres for a faster local loop.
- `workouts migrate` subcommand (`up`, `status`); the readiness component and the pool close hook from story 1 wired in `bootstrap`.
- `docs/adr/0004-translations-as-jsonb.md` (English in the source columns, other locales as `{"ru": {"name": …}}`; `COALESCE(translations #>> ARRAY[$locale,'name'], name)` for reads; blank value is a map delete; the EAV alternative and why it was rejected) and `0005-split-activity-tables.md` (no polymorphism; real foreign keys; training copies a template snapshot through `catalogapi`).

## Non-Goals

- any domain repository or use case (stories 4 onwards)
- data import from the MySQL database
- Atlas, golang-migrate or declarative schema tools

## Acceptance Criteria

- `workouts migrate up` on an empty database creates every table above; `workouts migrate status` lists the four migrations as applied; running `up` twice is a no-op
- `sqlc generate` output is committed and `make check` fails when a query or the schema changes without regenerating
- a smoke test inserts a user through generated code inside `InTx`, forces an error, and proves the row was rolled back
- two test packages using `testdb` run in parallel without interfering
- `go test ./...` passes with `TEST_DATABASE_URL` unset (container) and set (compose Postgres)
- ADR 0004 and 0005 are linked from the epic README

## Initial Tasks

- [ ] write the four migrations; review every `ON DELETE` against the Laravel migrations
- [ ] `sqlc.yaml`, first queries, generated packages committed
- [ ] `platform/postgres`: pool, runner, Transactor, error mapper, readiness component
- [ ] `platform/testdb` with the template-database strategy
- [ ] `workouts migrate`, `make check` additions (`sqlc diff`)
- [ ] ADR 0004, 0005

## Test Cases

- migrations apply and roll back cleanly (`down` to zero, `up` again)
- unique constraints: duplicate `(program_id, user_id)`, duplicate `(activity_id, "order")`, duplicate email each map to `apperr.Conflict`
- `ON DELETE SET NULL` on `workouts.template_id` and `ON DELETE RESTRICT` on `exercises.equipment_id` behave as declared
- Transactor commits on nil, rolls back on error and on panic
- `difficulty_value` round-trips `12.5` exactly

## Docs To Update

- `docs/architecture.md` (Go data model: the two schema departures, link to ADR 0004 and 0005)
- `services/api-go/README.md` (migrate and test-database instructions)

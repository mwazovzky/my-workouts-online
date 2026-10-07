# User Story: Shared YAML Catalog and Go Sync CLI

Move the shipped catalog to a repo-root `catalog/` directory of YAML files, keep the PHP copy in lockstep with a drift check, and port the validator and sync semantics to a `workouts catalog sync` command.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Admin Content Management
- Creates New Feature: No

## Problem Statement

The shipped catalog lives in PHP arrays with enum instances under `services/api-php/database/catalog/` (`equipment.php`, `categories.php`, `programs.php`, `retired.php`, `exercises/*.php`, `workout-templates/*.php`), readable only by a PHP runtime. The Go service needs the same content for its seed, its contract run and, later, its deploy, and the epic decided on one source of truth (decision 5). The validator (41 rules in `app/Services/Catalog/CatalogValidator.php`) and the sync algorithm (`CatalogSyncService.php`: match by slug, adopt by English name or `previous_names`, data wins for managed rows, admin-created rows untouched, retire with blockers) are the most behaviour-dense code in the backend and must be ported faithfully.

## User-Visible Outcome

None. The web SPA and iOS app behave identically; the Go dev database gets the real catalog.

## Scope

- One-time export to repo-root `catalog/`: `equipment.yaml`, `categories.yaml`, `programs.yaml`, `retired.yaml`, `exercises/*.yaml`, `workout-templates/*.yaml`, one YAML document per PHP file with identical keys (`slug`, `previous_names`, `translations` keyed by locale and field, `difficulty_unit`, `effort_type`, `rest_time_seconds`, `category_slugs`, `activities` with the `sets: N, effort, difficulty` shorthand kept as the authoring form, `schedule` for programs). Retirement reasons stay as YAML comments. Record the decision to keep the shorthand in ADR 0009.
- PHP: `catalog:export [--check]` artisan command that renders the PHP arrays to the same YAML shape; `--check` exits non-zero on any difference with the files in `catalog/`. A step in `tests.yml` runs `--check` until story 14 removes it. PHP keeps reading its own `.php` files in this story.
- `internal/catalog/adapters/catalogfile`: `gopkg.in/yaml.v3` loader for the directory, applying the same normalisation as `CatalogLoader.php` (`previous_names` default empty, `rest_time_seconds` default null, shorthand expansion, type order equipment → categories → exercises → workout templates → programs), with strict decoding so unknown keys fail.
- `internal/catalog/app/sync`: the validator (every rule from `CatalogValidator.php`, each with a stable rule id; table-driven tests over `catalog/testdata/invalid/<rule-id>/` fixtures where each fixture breaks exactly one rule; PHP's `tests/Unit/Catalog/ValidatorTest.php` is pointed at the same fixtures so both suites prove the same rule set) and the applier (index existing rows by slug and by normalised English name including retired exercises; adopt preferring non-retired then lowest id and warn on several matches; diff translations, attributes, sorted category ids, template activities and sets, program schedule in pivot order; restore retired exercises that are re-adopted; warnings when an equipment unit or exercise effort type changes with logged workout activities; retire in reverse dependency order with blockers reported as warnings and skipped; exercises soft-retired, other types hard-deleted; one transaction committed only when not `--dry-run` and not `--strict` with warnings; a report of counts per type and outcome, the change list and the warnings).
- `workouts catalog sync [--dry-run] [--strict]` with the same exit codes as `catalog:sync`; invalid catalog prints the violations and exits non-zero.
- `workouts seed` runs the sync after creating the demo admin; `contract-go.yml` therefore runs the real catalog.
- `docs/adr/0009-single-yaml-catalog-source.md`.

## Non-Goals

- switching the PHP loader to YAML (story 14)
- any change to catalog content
- admin CRUD endpoints (story 10); the applier writes through the repositories directly

## Acceptance Criteria

- `php artisan catalog:export --check` is green on the exported files and fails when a YAML value is edited
- `workouts catalog sync --dry-run` on an empty database reports every record as `created` and commits nothing; a second real run reports everything `unchanged`
- the Go applier and PHP `catalog:sync`, run against databases seeded from the same fixtures, produce the same report counts for the shared scenarios in `tests/Feature/Catalog/CatalogSyncTest.php` (adoption by name, adoption by `previous_names`, restore of a retired exercise, blocked retirement, unit-change warning, strict rollback)
- all 41 validator rules have a fixture under `catalog/testdata/invalid/` and both suites fail each fixture with its rule id
- `tests/Unit/Catalog/DataIntegrityTest.php` has a Go counterpart validating the shipped `catalog/` directory
- `contract-go` is still green with the real catalog loaded

## Initial Tasks

- [ ] export command in PHP, generate the YAML, review the diff by eye, `--check` CI step
- [ ] Go loader with strict decoding and normalisation tests
- [ ] validator rules and fixtures; wire PHP's validator tests to the fixtures
- [ ] applier with the Transactor; report type; `--dry-run`, `--strict`
- [ ] `catalog sync` subcommand; `seed` integration
- [ ] ADR 0009; docs

## Test Cases

- loader: each file type, shorthand expansion, unknown key rejected, missing directory
- validator: one fixture per rule id; the full shipped catalog passes
- applier: create, adopt by name, adopt by previous name, update translations only, restore retired, retire blocked by exercises, retire blocked by enrolled users, strict with warnings rolls back, dry run commits nothing
- mirrors `tests/Unit/Catalog/{Loader,Validator,DataIntegrity}Test.php` and `tests/Feature/Catalog/{CatalogSync,CatalogSyncCommand}Test.php`

## Docs To Update

- `docs/architecture.md` (catalog source of truth is `catalog/`; both sync commands)
- `docs/deployment.md` (Go sync command alongside `catalog:sync`)
- `CLAUDE.md` (catalog data location and the two commands)

# User Story: PHP Reads the Shared YAML Catalog

Switch the PHP `CatalogLoader` to the repo-root `catalog/*.yaml` files that the Go service already reads, delete the PHP data files and the drift check, and prove `catalog:sync` behaves exactly as before.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Admin Content Management (shipped catalog content)
- Creates New Feature: No

## Problem Statement

Since story 7 the shipped catalog exists twice: `services/api-php/database/catalog/*.php` (PHP arrays with enum instances, read by `app/Services/Catalog/CatalogLoader.php`) and `catalog/*.yaml` at the repo root (read by `internal/catalog/adapters/catalogfile`). A `catalog:export --check` job in `tests.yml` keeps them identical, which means every content change is made in YAML and then re-exported. The epic's decision 5 is one source of truth; this story completes it last, after the Go sync has been proven on the same data, so that PHP's production deploy path changes exactly once.

## User-Visible Outcome

None. `php artisan catalog:sync` on deploy produces the same database state from the YAML files as it did from the PHP files.

## Scope

- `services/api-php/app/Services/Catalog/CatalogLoader.php` reads YAML with `symfony/yaml` (already a transitive Laravel dependency; add it explicitly to `composer.json` with approval) from the path in `config/catalog.php`, which now defaults to `base_path('../../catalog')` in the monorepo and `/var/www/catalog` in the image; the loader maps string enum values back to `DifficultyUnit`, `EffortType`, `Weekday` instances and applies the same normalisation as today (`previous_names` default, `rest_time_seconds` default, the `sets`/`effort`/`difficulty` shorthand expansion), so `CatalogValidator` and `CatalogSyncService` are untouched.
- Delete `services/api-php/database/catalog/` and the `catalog:export` command plus its `--check` job in `.github/workflows/tests.yml`.
- `tests/Fixtures/catalog/` converted to YAML with the same contents; `tests/Unit/Catalog/{Loader,Validator,DataIntegrity}Test.php` and `tests/Feature/Catalog/*` run unchanged except for fixture paths; `DataIntegrityTest` now validates the shared `catalog/` directory, so it guards both backends.
- `docker/api/Dockerfile`: `COPY catalog /var/www/catalog` in the `api` target (build context is the repo root already); `.dockerignore` keeps `catalog/` included; the `app-dev` target reads the bind-mounted repo path.
- The shared invalid fixtures `catalog/testdata/invalid/*` (story 7) are consumed by `CatalogValidatorTest` with the same expected rule ids as the Go suite, so a rule drift fails both.
- `CLAUDE.md` Catalog Sync paragraph and the Common Commands note point at `catalog/`; `docs/architecture.md` and `docs/features/admin-content-management.md` describe the single source.

## Non-Goals

- changing catalog content or validator rules
- changing `catalog:sync` output, options or exit codes
- touching the Go loader (already reads the same files)

## Acceptance Criteria

- `docker compose exec -T app php artisan catalog:sync --dry-run` on a database synced from the old PHP files reports every record `unchanged`
- `php artisan test --compact tests/Unit/Catalog tests/Feature/Catalog` green with YAML fixtures; `DataIntegrityTest` passes against `catalog/`
- the full PHP suite stays at or above the 95% coverage gate
- `services/api-php/database/catalog/` no longer exists; `tests.yml` has no export check
- the `api` image contains `/var/www/catalog` and `catalog:sync` works inside it (deploy script unchanged)
- `go test ./internal/catalog/...` still green (no Go changes expected; run as a guard)
- Pint clean

## Initial Tasks

- [ ] YAML loader in `CatalogLoader`, enum mapping, config path
- [ ] convert `tests/Fixtures/catalog/` to YAML; point tests at it
- [ ] delete `database/catalog/*.php`, `catalog:export`, the CI drift job
- [ ] Dockerfile `COPY`, `.dockerignore` check
- [ ] shared invalid fixtures in `CatalogValidatorTest`
- [ ] `CLAUDE.md`, docs
- [ ] dry-run parity check against a database synced before the change

## Test Cases

- unit: loader parses every file type, normalises shorthand sets, maps enums, rejects unknown keys exactly as the PHP arrays did
- unit: validator produces the expected rule id for each `catalog/testdata/invalid/*` fixture
- feature: `CatalogSyncTest` (34 cases) and `CatalogSyncCommandTest` unchanged in behaviour
- integration: dry-run parity on a pre-change database

## Docs To Update

- `CLAUDE.md` (Catalog Sync paragraph, commands)
- `docs/architecture.md` (catalog data location)
- `docs/features/admin-content-management.md` (shipped catalog source)
- `docs/deployment.md` (image contents note)

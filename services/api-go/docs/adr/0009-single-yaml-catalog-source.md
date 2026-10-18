# 0009. One YAML Catalog for Both Backends, and Its Go Sync

- Status: accepted
- Date: 2026-10-12
- Story: [Go epic story 07](../../../../planning/epics/go-backend/stories/07-shared-yaml-catalog-and-go-sync-cli.md)

## Context

The shipped catalog (29 equipment items, 15 categories, 137 exercises, 36 workout templates, 14 programs) lives in PHP arrays under `services/api-php/database/catalog/`, with enum instances (`DifficultyUnit::Kilograms`), so only a PHP runtime can read it. `catalog:sync` applies it on every deploy and in seeding. The Go service needs the same content for its seed, its contract run and later its deploy, and the epic decided on a single source of truth (decision 9) rather than a second copy.

The validator (`CatalogValidator.php`) and the sync (`CatalogSyncService.php`: match by slug, adopt unmanaged rows by English name or a previous name, data files win, admin rows untouched, retire with blockers) are the most behaviour-dense code in the backend, so the Go port has to be provably the same.

## Decision

**Format.** The repository's `catalog/` directory holds the catalog as YAML, one file per PHP data file with the same keys (`slug`, `translations` by locale and field, `previous_names`, `difficulty_unit`, `equipment`, `effort_type`, `rest_time_seconds`, `categories`, `activities`, `schedule`; `retired.yaml` by type). Enums are their contract values (`kilograms`, `monday`). The authoring forms of the PHP files are kept:

- the set shorthand `{ exercise: squat, sets: 3, effort: 10 }` next to explicit `sets: [{ effort: 1800, difficulty: 1 }]`, because nearly every template is "N identical sets" and the expanded form would triple the files;
- optional keys left out (`previous_names`, an exercise's `rest_time_seconds`);
- comments (retirement reasons, file notes), which a format with no comments (JSON) would lose.

The catalog keeps the PHP keys where the story's draft said otherwise (`categories`, not `category_slugs`): the files are a port, not a redesign.

**Lockstep until PHP reads YAML.** `php artisan catalog:export` renders the PHP arrays as these files once; `catalog:export --check` (a step of the PHP CI job) compares every file with its PHP source as data, ignoring key order and comments, and fails on any difference, a missing file or an extra one. Export never overwrites an existing file without `--force`, so hand-added comments survive. Until story 14 switches PHP's loader to the YAML, a catalog change edits both copies. `symfony/yaml` (already installed through Sail) is pinned in `require-dev` for this.

**Go loader** (`internal/catalog/adapters/catalogfile`, `go.yaml.in/yaml/v3`, the YAML organisation's maintained continuation of `gopkg.in/yaml.v3`): reads the directory in PHP's order (files of `exercises/` and `workout-templates/` by name), applies `CatalogLoader`'s normalisation (defaults, shorthand expansion) and rejects any unknown key, which PHP silently ignores. Values keep their YAML types, so a wrong type is a validation violation with its rule rather than a decode error.

**Validator** (`internal/catalog/app/catalogsync`): every check of `CatalogValidator.php`, in the same order and with the same messages, except that the enum checks list the allowed values instead of naming a PHP enum. Each distinct check is a `Rule` with a stable id (40 of them; the story's "41" counted the reused slug checks twice). `catalog/testdata/valid/` is PHP's `tests/Fixtures/catalog` exported, and `catalog/testdata/invalid/<rule>/` holds, per rule, the files that differ from it; a test overlays each and requires exactly that rule (one documented consequence: equipment with an invalid unit also makes its exercises' equipment unknown, as in PHP). PHP's validator test moves to the same fixtures in story 14, when PHP reads YAML.

**Applier**: the same algorithm, step for step, over a `ports.CatalogStore` (the Postgres adapter writes the tables directly; there are no admin services yet), inside one transaction that is rolled back on a dry run or on warnings in strict mode. The report has the same outcomes, change lines and warning texts. One deliberate difference: an update of a template replaces its activities only when they changed, where PHP rewrites them on any change; the stored result is the same and ids stay stable.

**CLI**: `workouts catalog sync [--dry-run] [--strict]`, with `catalog:sync`'s output and exit codes; an unreadable or invalid catalog lists what is wrong and exits 1. `workouts seed` runs it after creating the demo admin, so the dev stack and the `contract-go` job have the real catalog. The directory is `CATALOG_DIR` (default `catalog`; `/src/catalog` in the dev container; the image copies `catalog/` to `/catalog`).

## Considered Options

1. Keep PHP files as the source and have Go execute or parse PHP: rejected, it ties the Go deploy to a PHP runtime.
2. JSON: rejected, no comments.
3. Expanded YAML (no shorthand, every key present): rejected, three times the size for no reader's benefit; the loaders normalise.
4. Strict typed decoding in Go (structs): rejected for the validator, because a wrong type would fail decoding instead of reporting PHP's rule; kept for keys.

## Consequences

- Good: one content source both backends can read; a content change is reviewed once (until story 14, twice with a CI check that they agree).
- Good: the Go sync is shown to be PHP's: the 34 `CatalogSyncTest` scenarios run against Go with the same counts and messages, every rule has a failing fixture, and the real catalog synced into both databases serves identical responses (exercises, programs, every program and template, en and ru).
- Bad: until story 14 an edit touches two files. The `--check` step makes forgetting one a CI failure.
- Bad: the sync's "logged workouts" warning reads training's `workout_activities` table from the catalog adapter, the one cross-module read in catalog; it only checks existence for a warning.
- Follow-up: done in story 14. PHP's `CatalogLoader` reads the YAML (unknown keys are errors there too), `CatalogValidatorTest` runs every `catalog/testdata/invalid/` fixture against PHP's `CatalogRule` ids, and the PHP data files, `catalog:export` and the `--check` step are gone; `symfony/yaml` moved to `require`. Both loaders return the same data for the shipped catalog, and a database synced from the PHP files dry-runs as unchanged from the YAML.

# Catalog

The shipped content of My Workouts: equipment, categories, exercises, workout templates and programs, in English and Russian. Both backends apply it to their databases on deploy and in seeding: the Go API reads these files (`workouts catalog sync`), the PHP API reads its copy in `services/api-php/database/catalog/` (`php artisan catalog:sync`) until the Go epic's story 14 switches it to these files too.

**Until then, every change goes into both copies.** CI runs `php artisan catalog:export --check`, which fails when the two differ in any value (comments and key order do not count).

## Files

| File | Holds |
| --- | --- |
| `equipment.yaml` | `slug`, `difficulty_unit` (`kilograms`, `pounds`, `plates`, `heart_rate_zone`, `none`), `translations.{en,ru}.name` |
| `categories.yaml` | `slug`, `translations.{en,ru}.name` |
| `exercises/*.yaml` | `slug`, `equipment` (slug), `effort_type` (`repetitions`, `duration`), `rest_time_seconds` (required for repetitions), `categories` (slugs), `translations.{en,ru}.{name,description}` |
| `workout-templates/*.yaml` | `slug`, `translations`, `activities`: `{ exercise, sets: N, effort, difficulty }` or `{ exercise, sets: [{ effort, difficulty }] }` |
| `programs.yaml` | `slug`, `translations`, `schedule`: `{ weekday, template }` |
| `retired.yaml` | removals by type: `{ slug, names }` |

Every record may list `previous_names`: older English names an existing row without a slug is adopted by, so its id and history survive. Files of a directory are read in file-name order. An unknown key is an error.

## Rules

The sync validates everything before writing anything; the full list of rules is in `services/api-go/internal/catalog/app/catalogsync/validate.go` (Go) and `services/api-php/app/Services/Catalog/CatalogValidator.php` (PHP). Among them: unique lowercase-hyphen slugs, every locale's name (and description where the type has one), resolvable references, every equipment item and category used by an exercise, a rest time for repetition exercises, no weight in template sets, heart-rate zones 1–5.

Removing a record from its file deletes nothing. To remove it, list it in `retired.yaml`: exercises are retired (hidden, history kept), other types are deleted only when nothing uses them.

## Applying

```bash
docker compose exec -T api-go go run ./cmd/workouts catalog sync --dry-run   # Go: preview
docker compose exec -T api-go go run ./cmd/workouts catalog sync             # Go: apply
docker compose exec -T app php artisan catalog:sync --dry-run                # PHP: preview
docker compose exec -T app php artisan catalog:export --check                # both copies agree
```

`--strict` writes nothing when the sync raises warnings (several rows match one name, a retirement is blocked, a measurement change would relabel logged workouts). Decisions: [ADR 0009](../services/api-go/docs/adr/0009-single-yaml-catalog-source.md).

## Test fixtures

`testdata/valid/` is a small valid catalog (PHP's `tests/Fixtures/catalog`, exported). `testdata/invalid/<rule>/` breaks one validation rule each: it holds only the files that differ from `testdata/valid/`, and the Go tests overlay them and require exactly that rule. A new rule needs a new fixture directory named after its id.

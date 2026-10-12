# 0004. Store Translations in a jsonb Column per Row

- Status: accepted
- Date: 2026-10-11
- Story: [Go epic story 02](../../../../planning/epics/go-backend/stories/02-schema-migrations-sqlc-and-test-harness.md)

## Context

Five catalog tables carry translatable text: `equipment` and `categories` (`name`), and `exercises`, `workout_templates` and `programs` (`name`, `description`). The API ships two locales, English (the source locale) and Russian, and the contract has two shapes:

- public endpoints return one localized value per field, chosen by `Accept-Language`, falling back to English;
- admin endpoints read and write every locale at once as `translations: {"name": {"en": …, "ru": …}}`; a blank value removes that locale so reads fall back.

The Laravel API keeps the English text in the row's own columns and every other locale in one polymorphic EAV table, `translations (translatable_type, translatable_id, locale, field, value)`. Reading a localized list means joining or eager-loading that table per model type. Nothing in the database ties a row to its parent: no foreign key, no cascade, and nothing stops a typo in `field`.

The Go service owns a fresh Postgres schema (epic decision 3), and its queries are hand-written SQL compiled by sqlc, so the shape has to read well in SQL and map to a typed column.

## Considered Options

1. Port the EAV table: `translations` with a polymorphic owner, joined per read.
2. One translation table per translatable table (`exercise_translations (exercise_id, locale, name, description)`), with real foreign keys.
3. A `translations jsonb` column on each translatable row, shaped `{"ru": {"name": …, "description": …}}`, with English left in `name` and `description`.

## Decision

Option 3. Each translatable table has `translations jsonb NOT NULL DEFAULT '{}'`, with a `CHECK (jsonb_typeof(translations) = 'object')`. The English source text stays in `name` and `description` and is never duplicated into the map. Other locales are keyed by locale, then field: `{"ru": {"name": "Присед", "description": "…"}}`.

- A localized read needs no join: the row comes with its map, and `LocalizedText.Resolve(locale)` picks the translation or falls back to the column. Story 6 resolves in Go rather than in SQL (`COALESCE(translations #>> ARRAY[@locale::text, 'name'], name)` would also work, unchanged for English and for a missing translation), for three reasons: the admin endpoints need the whole map, so public and admin reads share one query shape and one domain type; the exercise picker must sort like PHP (`mb_strtolower`, then byte order), which no Postgres collation reproduces; and a blank translation falls back in one place instead of in every query.
- Admin writes replace the row's map in the same `UPDATE` that changes `name`. A blank value is a missing key (`translations #- ARRAY['ru', 'description']`, or simply left out of the rebuilt map), which is exactly the fallback the contract describes.
- The domain type is `LocalizedText{Source string; Other map[Locale]string}` per field (story 6). The postgres adapter converts it to and from the column; no other layer sees JSON.

Option 1 was rejected for the problems it has in PHP: no referential integrity, a join or a second query on every localized read, and a string `type` column that sqlc cannot type. Option 2 is sound relational design but doubles the catalog tables (five more, each joined on every read and synced on every admin write) for data that is always read and written together with its row. Translations are a property of the row, not entities of their own.

Implemented in `migrations/00002_catalog.sql`.

## Consequences

- Good: a localized list is a single-table query; a row and its translations change atomically in one statement; deleting a row deletes its translations with no cascade to get wrong.
- Good: adding a locale is data, not schema: no migration, no new column.
- Bad: the database cannot enforce which fields a locale may hold, or that values are strings. The postgres adapter is the only writer and builds the map from `LocalizedText`, and its tests cover the round trip. Accepted.
- Bad: searching or sorting by a translated name happens in Go, or needs the `COALESCE` expression in SQL, which a plain index on `name` does not cover. The catalog is small (hundreds of rows), so loading it and sorting in memory is fine; an expression index per locale is the remedy if that changes.
- Done in story 6: the `LocalizedText` mapping and the localized public reads. Follow-up: story 10 the admin read and write of the full map; story 7 the catalog sync writing `translations` from the shared YAML.

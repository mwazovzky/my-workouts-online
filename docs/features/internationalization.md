# Internationalization (i18n) — EN + RU

## Overview

Full-app internationalization with English and Russian language support. All translations are backend-driven — no frontend i18n library. Users select their language on the Profile page.

---

## Design Principles

### 1. System Content vs. User Content

**System content** (exercises, categories, equipment, programs, workout templates) is created by admins and shared across all users → **translatable** via a polymorphic `translations` table with a `HasTranslations` trait. Original `name`/`description` columns are removed from these models. All display text — including English — lives in the `translations` table.

**User content** (workouts, future user-editable fields) is created by/for a specific user → **not translatable**. Stored once in the user's active language at creation time. When a workout is created from a template, the _translated_ template name is copied into `workout.name`. The user owns that string; no translation lookup is ever performed on it.

**Why:** A regular user cannot be expected to enter content in multiple languages. Translating user content would add complexity with no benefit. System content is finite, admin-controlled, and identical for all users — making it the right candidate for structured translation.

### 2. All Languages Treated Equally

No language is "special". English and Russian both live in the `translations` table as rows. Adding a new language = adding translation rows, zero schema changes.

### 3. Backend-First Translation

All translation logic lives in the Laravel backend. The API returns fully translated responses. No frontend i18n library. A `useTranslation()` composable reads translations from Inertia shared props. This ensures:

- Multiple frontends (SPA, mobile app, future clients) consume pre-translated data
- One source of truth for all translations (`lang/` directory + `translations` table)
- Swapping or adding frontends requires zero i18n re-implementation

### 4. Enum/Fixed-Value Translation

Code-level enums (`WorkoutStatus`) and stable DB enum values (weekdays) are translated via Laravel JSON files (`lang/*.json`), not the `translations` table. Resources send both raw values (for logic) and translated labels (for display).

---

## Translatable Models (System Content)

| Model             | Translatable Fields   | Column Removal               |
| ----------------- | --------------------- | ---------------------------- |
| `Exercise`        | `name`, `description` | Remove `name`, `description` |
| `Category`        | `name`                | Remove `name`                |
| `Equipment`       | `name`, `unit`        | Remove `name`, `unit`        |
| `Program`         | `name`, `description` | Remove `name`, `description` |
| `WorkoutTemplate` | `name`, `description` | Remove `name`, `description` |

## Non-Translatable Models (User Content)

| Model      | Reason                                                |
| ---------- | ----------------------------------------------------- |
| `Workout`  | Name copied from translated template at creation time |
| `User`     | Personal data                                         |
| `Role`     | Not displayed in UI                                   |
| `Activity` | No text fields (just order + FKs)                     |
| `Set`      | Numeric data only                                     |

---

## Architecture

### Translations Table (Polymorphic)

```
translations:
  id                (PK)
  translatable_type (string)     — e.g. App\Models\Exercise
  translatable_id   (unsignedBigInteger)
  locale            (string, 5)  — e.g. "en", "ru"
  field             (string)     — e.g. "name", "description"
  value             (text)

  UNIQUE (translatable_type, translatable_id, locale, field)
  UNIQUE (translatable_type, locale, field, value)  — prevents duplicate names
```

### HasTranslations Trait

Applied to all system models. Provides:

- `translations()` — `morphMany` relationship
- `translated(string $field): ?string` — returns translation for current locale, fallback to `en`, then `null`
- Virtual accessors: `$model->name`, `$model->description` — resolve via `translated()`
- `__toString()` — returns `$this->name`
- `createWithTranslations(array $translations, array $attributes = [])` — static seeder helper
- `scopeWithTranslations($query)` — eager-loads translations

### UI Strings

All UI strings (nav, buttons, headings, labels, toasts, empty states, confirm dialogs, validation messages, enum labels, weekday names) live in Laravel JSON files:

- `lang/en.json` — English (~120 keys)
- `lang/ru.json` — Russian (~120 keys)

Shared to the frontend via Inertia props in `HandleInertiaRequests` middleware. Consumed by a `useTranslation()` composable that provides a `t(key, replacements)` function.

### Locale Resolution

- `users.locale` column (`string(5)`, default `'en'`)
- `SetLocale` middleware reads `$request->user()->locale` and calls `App::setLocale()`
- Language selector on Profile page submits `PATCH /profile/locale`

---

## Implementation Plan

### Subtask 1: Language Selector + Locale Plumbing + Frontend `t()` Helper

**Deliverable:** Working language dropdown on Profile page. A `useTranslation()` composable is available. Two strings translated as proof-of-concept ("Profile" heading, "Save" button).

1. Migration: add `string('locale', 5)->default('en')` to `users`
2. Add `'locale'` to `$fillable` in User model, update UserFactory
3. Run `php artisan lang:publish` to create `lang/` directory
4. Create `lang/en.json` and `lang/ru.json` with initial keys
5. Create `SetLocale` middleware, register in `bootstrap/app.php`
6. Share `locale`, `availableLocales`, `translations` via `HandleInertiaRequests`
7. Create `ProfileLocaleRequest` Form Request (validates `locale` is `in:en,ru`)
8. Add `updateLocale()` to `ProfileController` + route `PATCH /profile/locale`
9. Create `resources/js/composables/useTranslation.js`
10. Create `UpdateLanguageForm.vue` partial, add to `Profile/Edit.vue`
11. Replace "Profile" heading and "Save"/"Saved." with `t()` as proof-of-concept
12. Tests: `PATCH /profile/locale` (valid, invalid, unauthenticated)

**Verify:** Profile → change to Русский → save → "Профиль" heading, "Сохранить" button.

### Subtask 2: Translations Table + Trait + Migration Redesign

**Deliverable:** System models lose `name`/`description` columns. All display text resolves from the translations table. DB content appears in Russian when locale is `ru`.

**2a. Translations infrastructure:**

1. Create `translations` table migration with unique indexes
2. Create `Translation` model + `TranslationFactory`
3. Create `HasTranslations` trait in `app/Models/Concerns/`

**2b. Redesign migrations (remove name/description columns):** 4. Modify category, equipment, exercise, program, workout_template migrations

**2c. Update models:** 5. Apply `HasTranslations` trait to all 5 system models 6. Add `$appends = ['name']`, remove text fields from `$fillable`

**2d. Rewrite seeders:** 7. Rewrite all seeders to use `createWithTranslations()` with EN + RU values 8. Update `WorkoutSeeder` to use `$template->translated('name')`

**2e. Update factories:** 9. Create `CategoryFactory`, update all other factories 10. Use `afterCreating` to auto-seed English translations

**2f. Update resources:** 11. Resources use virtual accessors (`$this->name`, `$exercise->name`) 12. Add `status_label` and `weekday_label` to resources

**2g. Update controllers & services:** 13. Eager-load `translations` in all relevant controllers 14. `WorkoutService::store()` copies `$workoutTemplate->name` (resolves via accessor)

**2h. Update tests:** 15. Update all resource tests and feature tests for new factory/seeder patterns 16. Add `HasTranslations` trait unit tests 17. Add feature test: resource returns Russian content when locale is `ru`

**Verify:** Switch to Russian → exercises, categories, equipment, programs in Russian. Start a workout → name in Russian.

### Subtask 3: Backend Validation & System Messages

**Deliverable:** Validation errors and auth messages appear in Russian.

1. Wrap custom messages in `__()` in form requests and custom rules
2. Create `lang/ru/auth.php` and `lang/ru/validation.php`
3. Add custom validation strings to `lang/ru.json`
4. Tests: submit invalid data with `locale=ru`, assert Russian errors

**Verify:** Switch to Russian → trigger validation error → error in Russian.

### Subtask 4: Frontend UI Translation

**Deliverable:** All remaining UI text in Russian. No new dependencies.

1. Add all remaining UI keys to `lang/en.json` and `lang/ru.json` (~120 keys total)
2. Replace hardcoded strings with `t()` in all layouts, pages, components, utils (~30 files)
3. `formatStatus()` uses `t()` lookup, `date.js` uses current locale
4. Status/weekday labels from resources or `t()` for static occurrences

**Verify:** Switch to Russian → full walkthrough of every page — everything in Russian.

---

## Dependency Graph

```
Subtask 1 → verify: locale pipe + proof-of-concept translated strings
    ↓
Subtask 2 → verify: DB content in Russian (exercises, categories, programs...)
    ↓
Subtask 3 → verify: validation errors in Russian
    ↓
Subtask 4 → verify: all UI text in Russian
```

---

## Key Decisions

- **No `name`/`description` columns on system models** — all display text in the `translations` table, including English
- **No frontend i18n library** — `useTranslation()` composable reads from Inertia shared props (~5-10KB payload)
- **Virtual accessors** (`$appends = ['name']`) — `$model->name` works everywhere, resolves from translations
- **`createWithTranslations()` helper** — clean seeder API for creating models with translations
- **Factory `afterCreating` callbacks** — auto-seed English translation so tests work without extra setup
- **Workout name** — copied from template's `translated('name')` at creation, stored as user content, never translated at read time
- **Migrations redesigned** — existing migrations modified directly (pre-production, no data to protect)
- **Unique index on `(translatable_type, locale, field, value)`** — replaces old `categories.name` unique constraint
- **Weekdays + statuses** — translated via `lang/*.json`, not the translations table
- **Polymorphic `translations` table** over JSON columns — normalized, supports unlimited languages without schema changes, suitable for future admin UI

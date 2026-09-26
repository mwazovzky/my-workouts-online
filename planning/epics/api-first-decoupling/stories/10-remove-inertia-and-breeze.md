# User Story: Remove Inertia and Breeze

Delete every server-rendered page path so the backend is API-only.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Auth & Profile
- Creates New Feature: No

## Problem Statement

After cutover the Inertia pages, Breeze web routes, and their tests are dead code that still shapes the backend.

## User-Visible Outcome

None; the old page routes disappear.

## Scope

- remove packages: `inertiajs/inertia-laravel`, `tightenco/ziggy`, `laravel/breeze`, `laravel/sanctum`, `laravel-vite-plugin`, `@inertiajs/vue3`
- remove `routes/web.php` page routes (keep health), `routes/auth.php`, page controllers, `HandleInertiaRequests`, `resources/views/app.blade.php`, `resources/js`, `LocaleController`, `PATCH /locale`, web `PUT /password`
- remove `assertInertia` page tests and Breeze redirect tests; confirm each behaviour is covered by an API test
- update `CLAUDE.md` and `docs/`

## Non-Goals

- none

## Acceptance Criteria

- `grep -ri inertia` and `grep -ri ziggy` in `services/api-php` return nothing
- `php artisan test --compact` passes
- `docs/` describes the API-only architecture

## Initial Tasks

- deletions and composer / npm updates
- test cleanup
- docs rewrite

## Test Cases

- full backend suite green
- `/` returns the SPA, not a Laravel response

## Docs To Update

- docs/architecture.md
- docs/pages-and-routes.md
- docs/testing.md
- docs/features/auth-and-profile.md
- CLAUDE.md

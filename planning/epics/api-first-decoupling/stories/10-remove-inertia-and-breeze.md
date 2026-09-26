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

- remove packages: `inertiajs/inertia-laravel`, `tightenco/ziggy`, `laravel/breeze`, `laravel/sanctum`; the whole legacy Node toolchain in `services/api-php` (package.json, vite, vitest, eslint, tailwind configs)
- `jwt` becomes the default and only guard; the `web` session guard, session middleware and CSRF are gone (operational routes load without a middleware group)
- `verification.verify` stays as a named route that forwards to the SPA, so emailed links can still be signed and validated
- favicons move to `apps/web/public`
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

# User Story: Me and Meta Endpoints

Replace the Inertia shared props with two JSON endpoints so clients can bootstrap without a server-rendered page.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: Platform
- Creates New Feature: No

## Problem Statement

`HandleInertiaRequests` shares the current user, locale, theme, and the locale list with every page. A standalone client has no way to obtain these.

## User-Visible Outcome

None directly; every client can restore the signed-in user and app options on load.

## Scope

- `GET /api/v1/me` returns `UserResource` (id, name, email, locale, theme_preference, is_admin, email_verified)
- `GET /api/v1/meta` returns `available_locales` and `theme_preferences`, public, cacheable
- `email_verified` added to `UserResource`

## Non-Goals

- UI translations (clients own them)

## Acceptance Criteria

- `/me` requires a valid access token and returns the resource shape from the contract
- `/meta` is public and returns the locale list from `config('app.available_locales')`

## Initial Tasks

- controllers, routes, resource change, tests

## Test Cases

- `/me` unauthenticated returns 401
- `/me` returns `is_admin` true for admins
- `/meta` returns both locales with labels

## Docs To Update

- docs/pages-and-routes.md
- docs/architecture.md (remove "Shared Auth" pattern)

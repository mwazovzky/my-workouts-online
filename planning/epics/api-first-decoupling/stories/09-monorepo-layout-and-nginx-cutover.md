# User Story: Monorepo Layout and nginx Cutover

Move the Laravel app into `services/api-php`, build the SPA into its own container, and route traffic through nginx.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: None
- Creates New Feature: Yes (Platform: deployment topology)

## Problem Statement

Laravel serves both the page shell and the API from one container. The SPA has no deploy path and the backend cannot be swapped independently.

## User-Visible Outcome

None; the app is served by the SPA container with the API behind `/api`.

## Scope

- move Laravel into `services/api-php/` (composer, artisan, tests, docker php image)
- `apps/web` Dockerfile: build stage plus nginx serving `dist` with SPA fallback
- `docker/nginx/default.conf.template`: `/` to web, `/api/*`, `/health*`, `/.well-known/*` to php-fpm
- `docker-compose.yml` and `docker-compose.prod.yml` updated; deploy scripts updated
- GitHub Actions: path filters per component; `apps/web` job (lint, test, build)
- HSTS and a Content Security Policy header from nginx

## Non-Goals

- deleting Inertia code (story 10)

## Acceptance Criteria

- `docker compose up` serves the SPA at `/` and the API at `/api/v1`
- production deploy script builds and starts both images
- CI runs backend jobs only when `services/api-php` or `contracts` change, and web jobs only when `apps/web` or `contracts` change

## Initial Tasks

- file move and path fixes
- Dockerfiles and compose changes
- nginx template
- workflows

## Test Cases

- local compose smoke test through nginx
- CSP produces no console violations on every page

## Docs To Update

- docs/deployment.md
- README.md

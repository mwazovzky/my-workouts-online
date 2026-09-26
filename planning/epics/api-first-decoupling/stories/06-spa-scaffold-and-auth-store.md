# User Story: SPA Scaffold and Auth Store

Create the standalone Vue application in `apps/web` with routing, state, i18n, and an authenticated API client.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Auth & Profile
- Creates New Feature: Yes (Platform: web client)

## Problem Statement

The frontend boots through `createInertiaApp` and depends on the Blade root view, Ziggy, and shared props. It cannot run without Laravel rendering the page.

## User-Visible Outcome

None yet; the SPA boots on its own, restores the session, and can call the API.

## Scope

- `apps/web` with Vite, Vue 3, vue-router, pinia, Tailwind, ESLint, Prettier, Vitest
- `src/i18n/locales/{en,ru}.json` copied from `lang/*.json`; `useTranslation` reimplemented over a small locale store with the same `t(key, replacements)` signature. vue-i18n was dropped: its message syntax (`|`, `@`, `{}`) conflicts with the Laravel-style flat catalogs and adds nothing for key-to-string maps
- pinia auth store: access token in memory, `login`, `logout`, `refresh`, `bootstrap` (calls `/me`)
- axios instance with bearer header, `Accept-Language`, `X-Requested-With`, and a refresh-and-retry interceptor on 401 `token_expired`
- route guards: `guest`, `auth`, `verified`, `admin`
- meta store loaded from `/meta`
- theme bootstrap from `localStorage` with a CSP-safe external script (`public/theme.js`)
- placeholder Welcome, Login, Dashboard and Verify Email pages so the shell is usable end to end; story 7 and 8 replace them with the real pages
- TypeScript client types generated from `contracts/openapi.yaml` with `openapi-typescript`
- Vite dev server proxies `/api` to the Laravel server

## Non-Goals

- porting pages (story 7)
- auth pages (story 8)

## Acceptance Criteria

- `npm run dev` in `apps/web` serves a shell that bootstraps `/me` and `/meta`
- an expired access token is refreshed transparently once, then the original request succeeds
- unit tests cover the auth store and interceptor

## Initial Tasks

- scaffold and config
- stores, client, guards, i18n
- generated types script
- tests

## Test Cases

- bootstrap with valid cookie restores user
- bootstrap with no session lands on guest route
- interceptor retries once and gives up on second 401
- guards redirect correctly for each role

## Docs To Update

- docs/architecture.md (frontend section, deferred to story 10 when the old frontend is gone)

# Web SPA

Standalone Vue 3 client for the `/api/v1` contract in [`contracts/openapi.yaml`](../../contracts/openapi.yaml).

## Stack

Vite · Vue 3 (`<script setup>`) · vue-router · Pinia · Tailwind v3 · Vitest

## Commands

```bash
npm install
npm run dev          # http://localhost:5174, proxies /api to VITE_API_PROXY_TARGET
npm run build        # production bundle in dist/
npm test             # Vitest
npm run lint:fix && npm run format
npm run api:types    # regenerate src/api/types.d.ts from the OpenAPI contract
```

Inside docker compose run them through the node service, e.g. `docker compose exec -w /var/www/html/apps/web node npm test`, with `VITE_API_PROXY_TARGET=http://nginx`.

## How it works

- **Auth** (`src/stores/auth.js`): the access token lives in memory only. On load the store calls `POST /auth/refresh` (the refresh token is an httpOnly cookie) and then `GET /me`. `src/lib/api.js` attaches the bearer token and `Accept-Language`, and on a 401 `token_expired` refreshes once and retries.
- **Routing** (`src/router`): route meta `guest`, `auth`, `verified`, `admin` drive the guards in `guards.js`.
- **Locale** (`src/stores/locale.js`): UI strings are client-owned catalogs in `src/i18n/locales/*.json` with Laravel-style `:placeholder` substitution via `useTranslation().t()`. The active locale is sent as `Accept-Language`; the profile locale is a synced preference.
- **Theme**: `public/theme.js` applies the stored preference before mount (CSP-friendly); `src/lib/theme.js` updates it at runtime.

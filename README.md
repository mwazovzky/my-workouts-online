# My Workouts Online

![tests](https://github.com/mwazovzky/my-workouts-online/actions/workflows/tests.yml/badge.svg?branch=main)
![code style](https://github.com/mwazovzky/my-workouts-online/actions/workflows/code-style.yml/badge.svg?branch=main)
![lint](https://github.com/mwazovzky/my-workouts-online/actions/workflows/lint.yml/badge.svg?branch=main)
![contract](https://github.com/mwazovzky/my-workouts-online/actions/workflows/contract.yml/badge.svg?branch=main)
![backend coverage](https://github.com/mwazovzky/my-workouts-online/raw/badges/backend.svg)
![frontend coverage](https://github.com/mwazovzky/my-workouts-online/raw/badges/frontend.svg)

> Product docs, features & architecture: [docs/](docs/README.md)

## Repository layout

```
apps/web/            Vue 3 SPA (vue-router, Pinia) — the user-facing web client
apps/ios/            SwiftUI iOS app (XcodeGen project, local Swift packages) — the native client
services/api-php/    Laravel 12 JSON API (php-fpm)
contracts/           OpenAPI contract shared by every client and backend
docker/              api + web images, nginx configs, compose support files
```

## Local development

**Prerequisites:** Docker + Docker Compose.

1. Copy env: `cp .env.example .env` (adjust if needed). The same file configures compose and the containers.
2. Build and start the stack:
   `docker compose up -d --build`
3. One-time app setup:
   - `docker compose exec -T app php artisan key:generate --show` and put the value in `.env` as `APP_KEY`
   - `docker compose exec -T app php artisan jwt:keys` and copy the two printed lines into `.env` (add `JWT_COOKIE_SECURE=false` for plain-http local use)
   - `docker compose up -d --force-recreate app` to pick up the new env values
   - `docker compose exec -T app php artisan migrate --seed` (roles, the catalog, and a demo admin `test@example.com` / `password` with demo workouts). Seeding is re-runnable; `php artisan catalog:sync` re-applies only the catalog. To start over use `migrate:fresh --seed`, which drops every table first
4. Open `http://localhost:8080` (from `APP_PORT`): nginx serves the SPA from the Vite dev server (with HMR) and proxies `/api` to php-fpm.

Logs (including the verification and password-reset emails, which use the `log` mailer locally) appear in `docker compose logs -f app`.

This mirrors the production topology (nginx in front of the SPA and the API) while keeping code bind-mounted for live edits.

**iOS app:** needs Xcode and XcodeGen on the Mac (`brew install xcodegen`). `cd apps/ios && xcodegen generate && open MyWorkouts.xcodeproj`; the Debug build talks to the dev stack above. Details in [apps/ios/README.md](apps/ios/README.md).

Useful commands:

```bash
docker compose exec -T app php artisan test --compact   # backend tests
docker compose exec -T app vendor/bin/pint --dirty       # PHP formatting
docker compose exec -T node npm test                     # SPA tests (apps/web)
docker compose exec -T node npm run lint:fix             # SPA lint
swift format lint --strict --recursive apps/ios          # iOS lint (host Xcode toolchain)
```

## Documentation

- [Product overview](docs/product.md)
- [Feature docs](docs/README.md)
- [Architecture](docs/architecture.md)
- [Pages & routes](docs/pages-and-routes.md)
- [Deployment runbook](docs/deployment.md)

## Production

Deployment and redeploy instructions live in [docs/deployment.md](docs/deployment.md).

## Releases

Web and API: tag `vX.Y.Z` on `main`; the `deploy` workflow builds and deploys. iOS: tag `ios-vX.Y.Z`; it runs the iOS suite and the build is archived and uploaded from Xcode. Both are described in [docs/deployment.md](docs/deployment.md).

## Database diagram

https://dbdiagram.io/d/workouts-68a1ae421d75ee360ae77ad8

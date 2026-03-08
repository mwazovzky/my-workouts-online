# My Workouts Online

![tests](https://github.com/mwazovzky/my-workouts-online/actions/workflows/tests.yml/badge.svg?branch=main)
![code style](https://github.com/mwazovzky/my-workouts-online/actions/workflows/code-style.yml/badge.svg?branch=main)
![lint](https://github.com/mwazovzky/my-workouts-online/actions/workflows/lint.yml/badge.svg?branch=main)
[![backend coverage](https://codecov.io/github/mwazovzky/my-workouts-online/graph/badge.svg?flag=backend)](https://app.codecov.io/github/mwazovzky/my-workouts-online)
[![frontend coverage](https://codecov.io/github/mwazovzky/my-workouts-online/graph/badge.svg?flag=frontend)](https://app.codecov.io/github/mwazovzky/my-workouts-online)

> Product docs, features & architecture: [docs/](docs/README.md)

## Local development

### Run with Artisan

```
php artisan serve
```

### Run with Docker Compose (recommended)

**Prerequisites:** Docker + Docker Compose.

1. Copy env: `cp .env.example .env` (adjust if needed).
2. Build and start the stack:
   `docker compose up -d --build`
3. One-time app setup:
   - `docker compose exec -T app php artisan key:generate`
   - `docker compose exec -T app php artisan migrate --seed`
4. Open:
   - App: `http://localhost:8080` (from `APP_PORT`)
   - Vite dev server: `http://localhost:5173`

This mirrors the production compose topology (nginx + php-fpm + mysql) while keeping code bind-mounted for live edits.

## Documentation

- [Product overview](docs/product.md)
- [Feature docs](docs/README.md)
- [Architecture](docs/architecture.md)
- [Pages & routes](docs/pages-and-routes.md)
- [Deployment runbook](docs/deployment.md)

## Production

Deployment and redeploy instructions live in [docs/deployment.md](docs/deployment.md).

### Linting / formatting

**Backend (PHP):**
```bash
./vendor/bin/pint
```

**Frontend (JavaScript/Vue):**
```bash
npm run lint        # Check for issues
npm run lint:fix    # Auto-fix issues
npm run format      # Format code with Prettier
```

## Database diagram

https://dbdiagram.io/d/workouts-68a1ae421d75ee360ae77ad8

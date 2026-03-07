# My Workouts Online

![tests](https://github.com/mwazovzky/my-workouts-online/actions/workflows/tests.yml/badge.svg?branch=main)
![code style](https://github.com/mwazovzky/my-workouts-online/actions/workflows/code-style.yml/badge.svg?branch=main)
![lint](https://github.com/mwazovzky/my-workouts-online/actions/workflows/lint.yml/badge.svg?branch=main)
[![codecov](https://codecov.io/gh/mwazovzky/my-workouts-online/graph/badge.svg)](https://codecov.io/gh/mwazovzky/my-workouts-online)

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

## Production

### First-time deployment (DigitalOcean)

See: [.ai/guidelines/deployment-guide.md](.ai/guidelines/deployment-guide.md)

### Redeploy (release engineer)

1. Build + push a new production image tag (run locally or in CI)
```bash
export IMAGE_NAME=mwazovzky/my-workouts-online
export IMAGE_TAG=prod-$(date +%Y%m%d%H%M)

docker login

docker buildx build \
  --platform linux/amd64 \
  --target web \
  --build-arg APP_ENV=production \
  -t ${IMAGE_NAME}:${IMAGE_TAG} \
  -f docker/php/Dockerfile .

docker push ${IMAGE_NAME}:${IMAGE_TAG}
echo $IMAGE_TAG
```

2. On the droplet: update IMAGE_TAG in .env.production, then redeploy
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml pull
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --force-recreate --remove-orphans
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T web php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T web php artisan optimize
```

3. Quick verify (run on the droplet)
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs --tail=80 web
curl -f http://localhost/health
curl -f http://localhost/health/ready
```

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

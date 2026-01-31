# DigitalOcean Deployment Guide (Docker Compose, prebuilt image)

## Strategy
1) Build a production Docker image locally (or in CI).
2) Push the image to Docker Hub.
3) On the droplet, pull that image and run it with `docker compose`.

## Repository layout rule (no duplication)
- Keep **one** `docker/` folder used by both local and production.
- Do **not** maintain parallel config files like `default.local.conf` and `default.prod.conf`.
- Use a **single** Nginx config template (example: `docker/nginx/default.conf.template`) and set the upstream via an env var:
  - Local: `PHP_FPM_UPSTREAM=app:9000`
  - Production: `PHP_FPM_UPSTREAM=127.0.0.1:9000`

## 0. Build & push the image (until CI takes over)
```bash
# Set your image name/tag (example)
export IMAGE_NAME=YOUR_DOCKERHUB_USER/PROJECT_NAME
export IMAGE_TAG=prod-$(date +%Y%m%d%H%M)

# Log in to Docker Hub
docker login

# Build the production web image (nginx + php-fpm baked)
# Use --platform to match your droplet's architecture (usually linux/amd64)
# Or build multi-platform with --platform linux/amd64,linux/arm64 --push
docker buildx build \
  --platform linux/amd64 \
  --target web \
  --build-arg APP_ENV=production \
  -t ${IMAGE_NAME}:${IMAGE_TAG} \
  -f docker/php/Dockerfile .

# Push the image
docker push ${IMAGE_NAME}:${IMAGE_TAG}

# Record the tag in your .env.production for the droplet
# IMAGE_NAME=${IMAGE_NAME}
# IMAGE_TAG=${IMAGE_TAG}
```

## 1. Create the droplet
- Create an Ubuntu LTS droplet.
- Add your SSH key during creation.

## 2. SSH into the droplet and create a deploy user
```bash
ssh root@YOUR_DROPLET_IP
adduser deploy
usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh
rsync -av ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
exit
ssh deploy@YOUR_DROPLET_IP
```

## 3. Install Docker + Docker Compose plugin on the droplet
```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker $USER
newgrp docker
docker --version
docker compose version
```

## 4. Get the project repo onto the droplet
```bash
cd ~
git clone YOUR_REPO_URL PROJECT_NAME
cd PROJECT_NAME
```

## 5. Create the production environment file on the droplet
```bash
cp .env.production.example .env.production
vim .env.production
```

Set these values in `.env.production`:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=http://YOUR_DROPLET_IP`
- `APP_KEY=...`
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`
- `DB_ROOT_PASSWORD=...`
- `IMAGE_NAME=YOUR_DOCKERHUB_USER/PROJECT_NAME`
- `IMAGE_TAG=prod-YYYYMMDDHHMM`

## 6. Login to Docker Hub on the droplet
```bash
docker login
```

## 7. Start the production stack (pulls the prebuilt image)
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml pull
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --force-recreate
docker compose --env-file .env.production -f docker-compose.prod.yml ps
```

Note: always include `--env-file .env.production` for *every* compose command on the droplet (including `down`), otherwise Compose will not have your DB credentials and image tag.

## 8. Run migrations and warm caches
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T web php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T web php artisan optimize
```

## 9. Verify health from inside the droplet
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs --tail=50 web
curl -f http://localhost/health
curl -f http://localhost/health/ready
```


## 10. Logs
```bash
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f --tail=200 web
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f --tail=200 mysql
```

## 11. Optional cleanup (disk space)
On small droplets, old images/build cache can fill the disk.

```bash
docker system df

# Safe: removes unused containers/networks/dangling images/cache (keeps named volumes)
docker system prune -f

# More aggressive: removes *all* unused images (older tags not currently running)
docker image prune -a -f

# ⚠️ Nuclear: also removes volumes (this deletes MySQL data)
docker system prune -a --volumes -f
```

## Troubleshooting (common deployment gotchas)
- If you see a platform mismatch warning (arm64 vs amd64), rebuild with `docker buildx build --platform linux/amd64`.


## Suggested Improvements

### Planned (not implemented yet)
- Build/push the production image via GitHub Actions (CD) instead of running `docker buildx build` manually.
- Add SSL certificates and HTTPS termination.

### Recommended (incremental)
- Nginx hardening: set `server_tokens off;` and add basic security headers in `docker/nginx/default.conf.template`.
- Health checks: prefer an HTTP healthcheck for the `web` container (e.g. `curl -f http://localhost/health`) and a MySQL healthcheck (`mysqladmin ping`).
- Production PHP tuning: set production opcache settings (e.g. `opcache.validate_timestamps=0`) in the production image.
- Build performance: use BuildKit cache mounts for npm/composer to speed up repeat builds (useful now, even more in CI).
- Image traceability: add OCI labels (source repo + version/tag) to the production image.

### Notes
- Avoid switching the production `web` container to a non-root user without reworking how nginx binds to port 80 (or using capabilities / non-privileged ports).


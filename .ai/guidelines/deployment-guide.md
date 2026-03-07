# DigitalOcean Deployment Guide (Docker Compose, prebuilt image)

## Strategy

1. Build a production Docker image locally (or in CI).
2. Push the image to Docker Hub.
3. On the droplet, pull that image and run it with `docker compose`.

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

# Record the tag in your .env on the droplet
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

## 5. Point your domain to the droplet (DNS setup)

Before deploying, create a DNS A record pointing your domain to the droplet's IP address.

## 6. Create the environment file on the droplet

Create a `.env` file in the project root on the droplet. Docker compose auto-loads `.env` for variable interpolation, and `docker-compose.prod.yml` passes it to the web container via `env_file`.

```bash
vim .env
```

Set these values in `.env` (use `.env.production` from the repo as a reference):

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=http://your-domain.com` (use `http://` initially; switch to `https://` after SSL is set up in step 11)
- `APP_KEY=...` (generate with `php artisan key:generate --show`)
- `DOMAIN_NAME=your-domain.com` (your actual domain, used for SSL and Nginx)
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`
- `DB_ROOT_PASSWORD=...`
- `SESSION_SECURE_COOKIE=false` (set to `true` after HTTPS is active — see step 11)
- `IMAGE_NAME=YOUR_DOCKERHUB_USER/PROJECT_NAME`
- `IMAGE_TAG=prod-YYYYMMDDHHMM`

See `.env.production` in the repo for all available settings (logging, cache, queue, mail, etc.).

## 7. Login to Docker Hub on the droplet

```bash
docker login
```

## 8. Start the production stack (HTTP first)

The site works on HTTP immediately. SSL certificates are obtained in the next step.

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d --force-recreate
docker compose -f docker-compose.prod.yml ps
```

Note: docker compose auto-loads `.env` from the project directory for variable interpolation. Always run compose commands from the project root so it can find `.env`.

## 9. Run migrations and warm caches

```bash
docker compose -f docker-compose.prod.yml exec -T web php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T web php artisan optimize
```

## 10. Obtain SSL certificate (Let's Encrypt)

With the site running on HTTP and DNS pointed to the droplet, obtain an SSL certificate.

**Test first with staging** (recommended to avoid rate limits):

```bash
docker run --rm \
  -v workouts_certbot_certs:/etc/letsencrypt \
  -v workouts_certbot_webroot:/var/www/certbot \
  certbot/certbot:latest \
  certonly --webroot -w /var/www/certbot \
  --email your-email@example.com \
  -d your-domain.com \
  --agree-tos --no-eff-email \
  --staging
```

If the staging test succeeds, obtain the real certificate (remove `--staging` and add `--force-renewal` to replace the staging cert):

```bash
docker run --rm \
  -v workouts_certbot_certs:/etc/letsencrypt \
  -v workouts_certbot_webroot:/var/www/certbot \
  certbot/certbot:latest \
  certonly --webroot -w /var/www/certbot \
  --email your-email@example.com \
  -d your-domain.com \
  --agree-tos --no-eff-email \
  --force-renewal
```

To include `www.your-domain.com`, add `-d www.your-domain.com` to the command.

## 11. Activate HTTPS

Recreate the web container so `start.sh` detects the new certificates and switches to the SSL Nginx config:

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate web
```

Verify HTTPS is working:

```bash
curl -I https://your-domain.com
# Should return HTTP/2 200 with Strict-Transport-Security header
```

Now update `.env` to use HTTPS and secure cookies:

```
APP_URL=https://your-domain.com
SESSION_SECURE_COOKIE=true
```

Recreate the web container again to pick up the `.env` changes:

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate web
```

## 12. Auto-renewal

Certificates expire every 90 days. Set up a cron job on the **host** to renew and reload Nginx.

Create the renewal script:

```bash
cat > ~/renew-certs.sh << 'SCRIPT'
#!/bin/bash
cd /home/deploy/workouts
docker run --rm \
  -v workouts_certbot_certs:/etc/letsencrypt \
  -v workouts_certbot_webroot:/var/www/certbot \
  certbot/certbot:latest renew --quiet
docker compose -f docker-compose.prod.yml exec -T web nginx -s reload
SCRIPT
chmod +x ~/renew-certs.sh
```

Add a cron job to run it every 12 hours:

```bash
(echo '0 */12 * * * /home/deploy/renew-certs.sh') | crontab -
crontab -l
```

Verify the renewal process works:

```bash
docker run --rm \
  -v workouts_certbot_certs:/etc/letsencrypt \
  -v workouts_certbot_webroot:/var/www/certbot \
  certbot/certbot:latest renew --dry-run
```

## 13. Verify health

```bash
docker compose -f docker-compose.prod.yml logs --tail=50 web
curl -f https://your-domain.com/health
curl -f https://your-domain.com/health/ready
```

## 14. Logs

```bash
docker compose -f docker-compose.prod.yml logs -f --tail=200 web
docker compose -f docker-compose.prod.yml logs -f --tail=200 mysql
```

## 15. Optional cleanup (disk space)

On small droplets, old images/build cache can fill the disk.

```bash
docker system df

# Safe: removes unused containers/networks/dangling images/cache (keeps named volumes)
docker system prune -f

# More aggressive: removes *all* unused images (older tags not currently running)
docker image prune -a -f

# WARNING: also removes volumes (this deletes MySQL data and SSL certificates)
docker system prune -a --volumes -f
```

## Troubleshooting (common deployment gotchas)

- If you see a platform mismatch warning (arm64 vs amd64), rebuild with `docker buildx build --platform linux/amd64`.

### SSL/HTTPS troubleshooting

- **"DNS problem: NXDOMAIN"** during certbot: DNS has not propagated yet. Verify with `dig +short your-domain.com` — it must return your droplet IP.
- **Rate limit errors**: Let's Encrypt has a limit of 5 duplicate certificates per week. Use `--staging` for testing to avoid hitting limits.
- **Certificate not found after certbot succeeds**: Make sure you restarted the web container (`docker compose ... restart web`) so `start.sh` can detect the cert files.
- **Mixed content warnings**: Ensure `APP_URL` in `.env` starts with `https://`.
- **Browser shows "Not Secure"**: The staging certificate is not trusted by browsers. Obtain the real certificate (without `--staging`).

## Suggested Improvements

### Planned (not implemented yet)

- Build/push the production image via GitHub Actions (CD) instead of running `docker buildx build` manually.

### Recommended (incremental)

- Nginx hardening: set `server_tokens off;` and add basic security headers in `docker/nginx/default.conf.template`.
- Health checks: prefer an HTTP healthcheck for the `web` container (e.g. `curl -f http://localhost/health`) and a MySQL healthcheck (`mysqladmin ping`).
- Production PHP tuning: set production opcache settings (e.g. `opcache.validate_timestamps=0`) in the production image.
- Build performance: use BuildKit cache mounts for npm/composer to speed up repeat builds (useful now, even more in CI).
- Image traceability: add OCI labels (source repo + version/tag) to the production image.

### Notes

- Avoid switching the production `web` container to a non-root user without reworking how nginx binds to port 80 (or using capabilities / non-privileged ports).

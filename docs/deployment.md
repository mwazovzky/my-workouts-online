# DigitalOcean Deployment Guide (Docker Compose, prebuilt image)

## Strategy

1. Code lands on `main` through the normal review and CI flow.
2. Pushing a semantic version tag such as `v1.4.0` triggers GitHub Actions.
3. GitHub Actions builds the production `web` image, tags it with both the release version and the release commit SHA, and pushes both tags to Docker Hub.
4. GitHub Actions SSHes into the droplet, checks out the matching release revision, updates `IMAGE_TAG`, pulls the new image, recreates services, runs migrations, warms caches, and verifies health.

`main` is the integration branch, not the production trigger. Production releases happen only from explicit version tags. Branch protection should still require the validation workflows (`tests`, `lint`, `code style`) before merge so only release-ready code is tagged.

## Repository Layout

Single `docker/` folder for both local and production. One Nginx config template (`docker/nginx/default.conf.template`), upstream set via env var (`PHP_FPM_UPSTREAM=app:9000` local, `127.0.0.1:9000` production).

## 0. Configure GitHub automation

The repository includes an automatic deploy workflow at `.github/workflows/deploy.yml`.

Required GitHub repository secrets:

- `DOCKERHUB_USERNAME`
- `DOCKERHUB_TOKEN`
- `PRODUCTION_HOST`
- `PRODUCTION_USER`
- `PRODUCTION_SSH_PRIVATE_KEY`
- `PRODUCTION_APP_PATH`

Optional GitHub repository secrets:

- `PRODUCTION_SSH_KNOWN_HOSTS` (recommended; if omitted, the workflow falls back to `StrictHostKeyChecking=accept-new`)

Required GitHub repository variables:

- `PRODUCTION_IMAGE_NAME` — for example `YOUR_DOCKERHUB_USER/my-workouts-online`

Optional GitHub repository variables:

- `PRODUCTION_HEALTHCHECK_URL` — for example `https://your-domain.com/health`
- `PRODUCTION_READYCHECK_URL` — for example `https://your-domain.com/health/ready`

The deploy workflow also supports manual `workflow_dispatch` runs. If you provide an existing `image_tag`, it deploys that tag without rebuilding. Use that for rollback or redeploying a known-good image.

Optional manual workflow input:

- `release_ref` — Git ref to check out on the droplet. Leave it blank to infer it from `image_tag`. For release-tag rollbacks, the default inference is usually correct.

The deploy workflow assumes the standard SSH port `22`.

## Release process

1. Merge tested code into `main`.
2. Create an annotated version tag from the commit you want to release, for example:

```bash
git checkout main
git pull --ff-only origin main
git tag -a v1.4.0 -m "Release v1.4.0"
git push origin v1.4.0
```

3. The `deploy` workflow builds `${PRODUCTION_IMAGE_NAME}:v1.4.0` and `${PRODUCTION_IMAGE_NAME}:sha-<commit>`, then deploys `v1.4.0` to production.

Release tags should be immutable. If you need a new production build, create a new version tag instead of moving an existing one.

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

The droplet keeps a working checkout because the deploy workflow fetches the repository and checks out the exact release ref before executing `scripts/deploy-production.sh`.

## 5. Point your domain to the droplet (DNS setup)

Before deploying, create a DNS A record pointing your domain to the droplet's IP address.

## 6. Create the environment file on the droplet

Create `.env` in the project root. Docker compose auto-loads it for variable interpolation and passes it to the web container via `env_file`.

```bash
vim .env
```

Required values (see `.env.production` in repo for full reference):

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
- `IMAGE_TAG=initial-tag` (the deploy workflow replaces this on every release)

## 7. Verify Docker access on the droplet

The deploy workflow performs `docker login` on every release using the GitHub secrets, so you do not need to keep a long-lived manual login session on the server.

You should still verify the deploy user can run Docker commands:

```bash
docker --version
docker compose version
docker ps
```

## 8. First production release (HTTP first)

After the droplet, repo checkout, `.env`, and GitHub secrets are configured, push a harmless version tag or run the deploy workflow manually with an existing image tag. GitHub Actions will:

1. Build the `web` image from `docker/php/Dockerfile`
2. Push it to Docker Hub as `${PRODUCTION_IMAGE_NAME}:vX.Y.Z` and `${PRODUCTION_IMAGE_NAME}:sha-<commit>`
3. SSH into the droplet
4. Check out the matching release revision on the droplet
5. Update `IMAGE_TAG` in `.env`
6. Ensure MySQL is running, pull the new `web` image, and recreate only the `web` service
7. Execute migrations and `php artisan optimize`

If you need to start the stack manually before CI/CD is ready, the commands are still:

```bash
docker compose -f docker-compose.prod.yml up -d mysql
docker compose -f docker-compose.prod.yml pull web
docker compose -f docker-compose.prod.yml up -d web
docker compose -f docker-compose.prod.yml ps
```

Always run compose commands from the project root so it finds `.env`.

## 9. Post-deploy application steps

The deploy workflow runs these automatically on every release:

```bash
docker compose -f docker-compose.prod.yml up -d mysql
docker compose -f docker-compose.prod.yml pull web
docker compose -f docker-compose.prod.yml up -d --no-deps --force-recreate web
docker compose -f docker-compose.prod.yml exec -T web php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T web php artisan optimize
```

Run them manually only for troubleshooting or if you intentionally bypass the workflow.

## 10. Obtain SSL certificate (Let's Encrypt)

**Test with staging first** (avoids rate limits):

```bash
docker run --rm \
  -v my-workouts-online_certbot_certs:/etc/letsencrypt \
  -v my-workouts-online_certbot_webroot:/var/www/certbot \
  certbot/certbot:latest \
  certonly --webroot -w /var/www/certbot \
  --email your-email@example.com \
  -d your-domain.com \
  --agree-tos --no-eff-email \
  --staging
```

If staging succeeds, obtain the real certificate:

```bash
docker run --rm \
  -v my-workouts-online_certbot_certs:/etc/letsencrypt \
  -v my-workouts-online_certbot_webroot:/var/www/certbot \
  certbot/certbot:latest \
  certonly --webroot -w /var/www/certbot \
  --email your-email@example.com \
  -d your-domain.com \
  --agree-tos --no-eff-email \
  --force-renewal
```

To include `www.your-domain.com`, add `-d www.your-domain.com` to the command.

## 11. Activate HTTPS

Recreate web container so `start.sh` detects certificates and switches to SSL config:

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate web
```

Verify HTTPS is working:

```bash
curl -I https://your-domain.com
# Should return HTTP/2 200 with Strict-Transport-Security header
```

Update `.env`:

```ini
APP_URL=https://your-domain.com
SESSION_SECURE_COOKIE=true
```

Recreate to pick up changes:

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate web
```

## 12. Auto-renewal (every 90 days)

Create the renewal script:

```bash
cat > ~/renew-certs.sh << 'SCRIPT'
#!/bin/bash
cd /home/deploy/my-workouts-online
docker run --rm \
  -v my-workouts-online_certbot_certs:/etc/letsencrypt \
  -v my-workouts-online_certbot_webroot:/var/www/certbot \
  certbot/certbot:latest renew --quiet
docker compose -f docker-compose.prod.yml exec -T web nginx -s reload
SCRIPT
chmod +x ~/renew-certs.sh
```

Cron job (every 12 hours):

```bash
(echo '0 */12 * * * /home/deploy/renew-certs.sh') | crontab -
crontab -l
```

Verify:

```bash
docker run --rm \
  -v my-workouts-online_certbot_certs:/etc/letsencrypt \
  -v my-workouts-online_certbot_webroot:/var/www/certbot \
  certbot/certbot:latest renew --dry-run
```

## 13. Verify health

```bash
docker compose -f docker-compose.prod.yml logs --tail=50 web
curl -f https://your-domain.com/health
curl -f https://your-domain.com/health/ready
```

If `PRODUCTION_HEALTHCHECK_URL` and `PRODUCTION_READYCHECK_URL` are configured in GitHub, the deploy workflow performs these checks automatically and fails the release if they do not pass.

## 14. Logs

```bash
docker compose -f docker-compose.prod.yml logs -f --tail=200 web
docker compose -f docker-compose.prod.yml logs -f --tail=200 mysql
```

## 15. Optional cleanup

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

### Automatic deploy troubleshooting

- **Deploy workflow fails before SSH**: verify `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`, `PRODUCTION_HOST`, `PRODUCTION_USER`, `PRODUCTION_SSH_PRIVATE_KEY`, `PRODUCTION_APP_PATH`, and `PRODUCTION_IMAGE_NAME` are configured in GitHub.
- **SSH connection fails**: verify the deploy user, host, SSH key, and `PRODUCTION_SSH_KNOWN_HOSTS` value. If you omit known hosts, the workflow uses `accept-new`.
- **`git checkout --detach` fails on the droplet**: the requested release ref does not exist on the server after fetch, or the working copy has conflicting local changes. Verify the tag or ref name and make sure the droplet checkout is clean.
- **`IMAGE_TAG entry was not found in .env`**: add `IMAGE_TAG=...` to the droplet `.env`; the deploy script updates that line in place.
- **Automatic deploy succeeds but app is unhealthy**: inspect the `Deploy Production` job logs first, then run the manual log and curl commands from steps 13 and 14 on the droplet.

### Rollback

The deploy workflow supports manual `workflow_dispatch` runs with an `image_tag` input.

Use rollback like this:

1. Open GitHub Actions.
2. Run the `deploy` workflow manually.
3. Provide a previously pushed release tag such as `v1.3.2`.
4. Leave `release_ref` blank unless you need to deploy an image tag that does not directly match the Git ref.
5. The workflow skips the build job and deploys that exact tag.

### SSL/HTTPS troubleshooting

- **"DNS problem: NXDOMAIN"** during certbot: DNS has not propagated yet. Verify with `dig +short your-domain.com` — it must return your droplet IP.
- **Rate limit errors**: Let's Encrypt has a limit of 5 duplicate certificates per week. Use `--staging` for testing to avoid hitting limits.
- **Certificate not found after certbot succeeds**: Make sure you restarted the web container (`docker compose ... restart web`) so `start.sh` can detect the cert files.
- **Mixed content warnings**: Ensure `APP_URL` in `.env` starts with `https://`.
- **Browser shows "Not Secure"**: The staging certificate is not trusted by browsers. Obtain the real certificate (without `--staging`).

# DigitalOcean Deployment Guide (Docker Compose, prebuilt image)

## Strategy

1. Code lands on `main` through the normal review and CI flow.
2. Pushing a semantic version tag such as `v1.4.0` triggers GitHub Actions.
3. GitHub Actions builds two production images, `api` (php-fpm, from `docker/api/Dockerfile`) and `web` (nginx serving the SPA build and fronting the API, from `docker/web/Dockerfile`), tags each with `api-`/`web-` plus the release version and the commit SHA, and pushes them to Docker Hub.
4. GitHub Actions SSHes into the droplet, checks out the matching release revision, updates `IMAGE_TAG`, pulls the new image, recreates services, runs migrations, warms caches, and verifies health.

`main` is the integration branch, not the production trigger. Production releases happen only from explicit version tags. Branch protection should still require the validation workflows (`tests`, `lint`, `code style`) before merge so only release-ready code is tagged.

## Repository Layout

Monorepo: `services/api-php` (Laravel API), `apps/web` (Vue SPA), `contracts/openapi.yaml`, `docker/`. One `docker/` folder serves local and production:

- `docker/api/Dockerfile` — php-fpm image (`api`, `worker`)
- `docker/web/Dockerfile` — builds `apps/web` and serves it with nginx; `docker/nginx/api-locations.conf` routes `/api/*`, `/health*`, `/up` and `/.well-known/jwks.json` to php-fpm over FastCGI (`PHP_FPM_UPSTREAM=api:9000`, re-resolved through Docker DNS every 5 s, so recreating `api` alone never leaves `web` pointing at a stale IP), everything else is the SPA with history fallback; `well-known-locations.conf` serves the Apple App Site Association file rendered by `docker/web/start.sh` from `APPLE_APP_ID`; `security-headers.conf` sets CSP and friends
- `docker/nginx/dev.conf.template` — local nginx: same API routing, `/` proxied to the Vite dev server

Swapping the backend implementation later means changing `PHP_FPM_UPSTREAM` (or the proxy in `api-locations.conf`); the web image and clients stay as they are.

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
- `PRODUCTION_PUBLIC_URL` — the public origin, for example `https://your-domain.com`; the web image build fails without it. `robots.txt`, `sitemap.xml` and other absolute URLs use it

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
git tag -a v0.3.9 -m "Release v0.3.9 | Short summary"
git push origin v0.3.9
```

3. The `deploy` workflow builds `${PRODUCTION_IMAGE_NAME}:api-v1.4.0`, `:web-v1.4.0` and the matching `:api-sha-<commit>` / `:web-sha-<commit>` tags, then deploys `v1.4.0` to production (the droplet's `IMAGE_TAG` is the bare version; compose adds the `api-`/`web-` prefixes).

Release tags should be immutable. If you need a new production build, create a new version tag instead of moving an existing one.

## Release runbook

Pre-release:

- Confirm the target commit is already on `main`.
- Confirm the required GitHub workflows for that commit are green.
- Choose the next immutable version tag.

Release commands:

```bash
git checkout main
git pull --ff-only origin main
git tag -a v0.2.10 -m "Release v0.2.10| Test route"
git push origin v0.2.10
```

Release verification:

- Confirm the `deploy` workflow starts from the new tag in GitHub Actions.
- Confirm the workflow completes successfully.
- Confirm the production home page and health endpoints respond successfully.
- Confirm the droplet `.env` contains the expected `IMAGE_TAG`.

Rollback:

- Run the `deploy` workflow manually.
- Set `image_tag` to the last known-good release tag, for example `v1.3.2`.
- Leave `release_ref` blank unless the image tag does not match the Git ref you need.

## iOS release (TestFlight / App Store)

The iOS app ships independently of the web and API. Its tags are `ios-vX.Y.Z`; the `deploy` workflow listens only to `v*`, so an iOS tag never deploys the web and API, and a web release never ships the app.

### Prerequisites (one-time)

- An Apple Developer Program membership. Note the **Team ID** (developer.apple.com → Membership details).
- The bundle identifier `online.my-workouts.app` registered under Identifiers with the **Associated Domains** capability, and an app record in App Store Connect with the same identifier. The identifier cannot change after the first upload.
- `DEVELOPMENT_TEAM = <Team ID>` in `apps/ios/Config/Base.xcconfig` (not a secret; committing it lets everyone sign with the same team).
- On the droplet, `APPLE_APP_ID=<Team ID>.online.my-workouts.app` in `.env`, then a web release so nginx serves the Apple App Site Association file with it; verify with the curl in "Verify health". Universal Links only work once this matches the signed app.
- A demo account for App Review on production, created without the email round-trip:
  `docker compose -f docker-compose.prod.yml exec -T api php artisan user:create "App Review" review@example.com '<password>' --verified`.
  Put the credentials in the review notes of every submission.

### Per release

1. Merge to `main`. Bump `MARKETING_VERSION` in `apps/ios/Config/Versions.xcconfig` (and reset `CURRENT_PROJECT_VERSION` to 1); every further upload of the same version increments `CURRENT_PROJECT_VERSION`, because App Store Connect rejects a build number it has seen.
2. Tag and push:
   ```bash
   git tag -a ios-v1.0.0 -m "iOS release 1.0.0 | Short summary"
   git push origin ios-v1.0.0
   ```
   The `ios` workflow runs the full suite including the XCUITest smoke flows for this tag; `deploy` must not run.
3. In Xcode on the tagged commit: `xcodegen generate`, open the project, select "Any iOS Device", Product → Archive, then Distribute → App Store Connect → Upload (automatic signing with the team above).
4. In App Store Connect, add the build to the internal TestFlight group, install it on a device, and complete the acceptance flow against production: register, verify through the emailed link (it must open the app), enroll, log and complete a workout, log out.
5. Submit for review: attach the build, fill the "What's new" text in English and Russian, keep the demo account in the review notes.

### Store listing checklist

- Support URL `https://my-workouts.online/support`, privacy policy URL `https://my-workouts.online/privacy` (both public SPA pages; the app also links to them from the Profile tab).
- App Privacy answers mirror `apps/ios/App/PrivacyInfo.xcprivacy`: name, email and fitness data collected, linked to the user, used for app functionality, no tracking.
- Export compliance is declared in `Info.plist` (`ITSAppUsesNonExemptEncryption` false: HTTPS only).
- Account deletion is available in the app (Profile → Delete Account), as guideline 5.1.1 requires.
- Sign in with Apple is not required while the only login is email and password.
- Screenshots: 6.7" and 6.1" iPhone sizes at minimum; the simulator screenshots from `xcrun simctl io booted screenshot` are acceptable.

### Rollback

TestFlight and App Store builds cannot be withdrawn once users have them; ship a fixed build with a higher version. A release can be removed from sale in App Store Connect while a fix is prepared.

## Upgrading an existing droplet to the SPA + API release

The first release from the API-first refactor changes the production topology. Do these once, before pushing the release tag:

1. **Update `.env` on the droplet** (see step 6): add `JWT_PRIVATE_KEY`, `JWT_PUBLIC_KEY` and `JWT_COOKIE_SECURE=true` (HTTPS is already active), and `FRONTEND_URL=https://your-domain.com`. `SESSION_*` values are no longer used and can be removed. `IMAGE_NAME` stays the same; the workflow now pushes `api-` and `web-` prefixed tags to that repository.
2. **Mail must work.** Email verification is enforced from this release; new registrations must click the emailed link. Existing accounts are marked verified by a migration.
3. **Branch protection.** The frontend check is now named "Web SPA Tests", the lint workflow has a second job "OpenAPI contract", and there is a new `contract` workflow. Update required checks accordingly.
4. **Push the tag.** The deploy script pulls `api`, `web`, `worker` and `alloy`, recreates `api`, runs migrations there (this drops the old Sanctum `personal_access_tokens` table and creates `refresh_tokens`), then recreates `web` and `worker`. Old containers named `web` from the single-image era are replaced in place.
5. **Verify** with step 13. Every user has to sign in again (sessions no longer exist).

Rollback to a pre-cutover tag is not supported by the workflow: those images used the single-image compose layout. Keep the previous droplet checkout's `docker-compose.prod.yml` at hand if you need it.

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

Create `.env` in the project root. Docker compose auto-loads it for variable interpolation and passes it to the `api` and `worker` containers via `env_file`; the `web` container (nginx) only needs `DOMAIN_NAME`.

```bash
vim .env
```

Required values (see `.env.example` in the repo for the full reference):

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=http://your-domain.com` (use `http://` initially; switch to `https://` after SSL is set up in step 11)
- `APP_KEY=...` (generate with `docker run --rm ${IMAGE_NAME}:api-<tag> php artisan key:generate --show`, or `php artisan key:generate --show` where PHP is installed)
- `DOMAIN_NAME=your-domain.com` (your actual domain, used for SSL and Nginx)
- `APPLE_APP_ID=TEAMID.bundle.id` (optional; the iOS app's Apple Team ID and bundle identifier. nginx serves `/.well-known/apple-app-site-association` from it so email links open the app. Leave it empty until the app is published, and change it here when the Team ID or bundle id changes; the next release re-renders the file)
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`
- `DB_ROOT_PASSWORD=...`
- `FRONTEND_URL=...` (where email links point; same as `APP_URL`, since nginx serves the SPA on the same origin)
- `JWT_PRIVATE_KEY=...` and `JWT_PUBLIC_KEY=...` (generate once with `docker run --rm ${IMAGE_NAME}:api-<tag> php artisan jwt:keys`; rotating them invalidates every issued access token and signs every user out)
- `JWT_COOKIE_SECURE=false` (set to `true` after HTTPS is active — see step 11)
- `IMAGE_NAME=YOUR_DOCKERHUB_USER/PROJECT_NAME`
- `IMAGE_TAG=initial-tag` (the deploy workflow replaces this on every release)
- `APP_VERSION=dev` (the deploy script replaces this with the release tag on every release)

Optional: `API_RATE_LIMIT_PER_MINUTE` (default 60 requests per minute per client IP on `/api`, both backends), `LOKI_*` for the `alloy` log shipper, `RESEND_WEBHOOK_SECRET` and `MAIL_INBOUND_FORWARD_TO` for the support inbox (see step 9).

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

1. Build the `api` and `web` images from `docker/api/Dockerfile` and `docker/web/Dockerfile`
2. Push them to Docker Hub as `${PRODUCTION_IMAGE_NAME}:api-vX.Y.Z`, `:web-vX.Y.Z` and the matching `:api-sha-<commit>` / `:web-sha-<commit>` tags
3. SSH into the droplet
4. Check out the matching release revision on the droplet
5. Update `IMAGE_TAG` in `.env`
6. Ensure MySQL is running, pull the new images, recreate `api`, run migrations and `php artisan optimize` in it, then recreate `web` and `worker`
7. Apply the catalog data files with `php artisan catalog:sync` (see step 9)

If you need to start the stack manually before CI/CD is ready, the commands are still:

```bash
docker compose -f docker-compose.prod.yml up -d mysql
docker compose -f docker-compose.prod.yml pull api web
docker compose -f docker-compose.prod.yml up -d api web
docker compose -f docker-compose.prod.yml ps
```

Always run compose commands from the project root so it finds `.env`.

## 9. Post-deploy application steps

The deploy workflow runs these automatically on every release:

```bash
docker compose -f docker-compose.prod.yml up -d mysql
docker compose -f docker-compose.prod.yml pull api web
docker compose -f docker-compose.prod.yml up -d --no-deps --force-recreate api
docker compose -f docker-compose.prod.yml exec -T api php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T api php artisan optimize
docker compose -f docker-compose.prod.yml up -d --no-deps --force-recreate web
docker compose -f docker-compose.prod.yml up -d --no-deps --force-recreate worker
docker compose -f docker-compose.prod.yml exec -T api php artisan catalog:sync
```

Run them manually only for troubleshooting or if you intentionally bypass the workflow.

### Catalog

Production is never seeded with `db:seed`. The catalog (equipment, categories, exercises, workout templates, programs) comes from the YAML files in the repository's `catalog/`, which the `api` image carries at `/var/www/catalog` (`CATALOG_PATH`), applied by `catalog:sync` at the end of every deploy. The `Admin` and `User` roles come from a migration.

- The sync resets shipped records to the data files, so an edit made to one of them in the admin UI lasts only until the next deploy. Records created in the admin UI are not touched.
- The new `api` container starts before `migrate` runs. A release whose migration moves data can therefore serve incomplete admin payloads for the few seconds in between; do not edit catalog content in the admin UI during a deploy.
- A data migration that finds invalid rows stops before writing and names them (for example, a catalog record without an English name). Fix the rows, then re-run the deploy.
- The sync runs after all services are recreated. If it fails, the deploy fails, nothing is written, and the stack keeps running on the new release.
- The Go API (not deployed yet) applies the same content from the repository's `catalog/` YAML with `workouts catalog sync [--dry-run] [--strict]`, which has the same rules, report and exit codes; story 13 of the Go epic adds it to the deploy.
- To preview a release's catalog changes without writing, run it with `--dry-run`. `adopted` means an existing row was matched by name and keeps its id; `created` for a record that already exists in production means its name differs and needs a `previous_names` entry in the data file.

```bash
docker compose -f docker-compose.prod.yml exec -T api php artisan catalog:sync --dry-run
```

### First admin

There is no seeded admin account in production. Register through the app, verify the email, then grant the role on the droplet:

`php artisan user:create {name} {email} {password} [--verified]` creates an account without the email round-trip, for example the App Review demo account or a first admin before `user:grant-admin`.

```bash
docker compose -f docker-compose.prod.yml exec -T api php artisan user:grant-admin you@example.com
```

The command refuses an unknown or unverified email and is safe to repeat.

### Support inbox

Mail sent to any address on the domain (for example `support@`) is received by Resend and relayed to a personal inbox by the API:

1. In Resend, enable receiving on the domain and add the `MX @` record it shows to the DNS provider (DigitalOcean).
2. In Resend → Webhooks, add `https://your-domain.com/api/webhooks/resend` with the `email.received` event and copy its signing secret.
3. In the droplet `.env`, set `RESEND_WEBHOOK_SECRET=whsec_...` and `MAIL_INBOUND_FORWARD_TO=you@example.com`, then recreate `api` and `worker`.

The endpoint rejects every request while `RESEND_WEBHOOK_SECRET` is empty and queues a job per email; the `worker` fetches the body and attachments from Resend and sends them on from `MAIL_FROM_ADDRESS` with `Reply-To` set to the original sender. Forwarding failures retry and are logged by the `worker` service. Received emails stay visible in the Resend dashboard either way.

The Go API implements the same endpoint and forward ([ADR 0013](../services/api-go/docs/adr/0013-support-inbox-and-jobs.md)) and reads the same variables (`RESEND_KEY`, `RESEND_WEBHOOK_SECRET`, `MAIL_INBOUND_FORWARD_TO`, `MAIL_FROM_ADDRESS`, `MAIL_MAILER`), so one `.env` serves both. Its queue is River in the Go Postgres database, worked by a `worker-go` container running `workouts work`, and a redelivered webhook is recognised by its email id for a week. It is not deployed yet: nginx sends the webhook to PHP until story 13 of the Go epic adds `worker-go` to the production compose file and the backend switch.

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

Recreate the web container so `docker/web/start.sh` detects certificates and switches to the SSL config:

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
FRONTEND_URL=https://your-domain.com
JWT_COOKIE_SECURE=true
```

Recreate the containers that read `.env` to pick up the changes:

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate api worker
```

## 12. Auto-renewal (every 90 days)

The renewal script lives at `scripts/renew-certs.sh` in the repo. Every run of `scripts/deploy-production.sh` installs it onto the droplet at `/home/deploy/renew-certs.sh` (mode `0755`), so it stays in sync with the repo on every release. You do not need to paste it manually.

What the script does:

- Logs each run to `/home/deploy/renew-certs.log` with timestamped start/finish markers.
- Runs `certbot renew --no-random-sleep-on-renew` against the persistent `certbot_certs` / `certbot_webroot` volumes.
- Reloads nginx in the `web` container so it picks up renewed certs.
- Uses `set -euo pipefail` and intentionally does **not** pass `--quiet`, so any failure is visible in the log.

Why `--no-random-sleep-on-renew` matters: certbot's default behavior under a non-TTY shell (cron, `docker run` without `-it`) adds a random sleep of up to 8 minutes to spread load on Let's Encrypt. Combined with `--quiet` — which an earlier version of this script used — that sleep was completely silent and indistinguishable from a hang, and any actual renewal error was suppressed too. Dropping both is what makes manual smoke tests and cron failures observable.

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

Smoke test the wrapper script and confirm the cert that's actually being served:

```bash
~/renew-certs.sh
tail -n 40 ~/renew-certs.log

echo | openssl s_client -connect your-domain.com:443 -servername your-domain.com 2>/dev/null \
  | openssl x509 -noout -subject -issuer -dates
```

A healthy outcome on the first run is "Cert not due for renewal" in the log plus a `notAfter` ~90 days in the future. After the next 12-hour cron tick, confirm `~/renew-certs.log` has a fresh timestamped entry — that proves cron is wired to the new script and its `PATH` can find `docker`.

## 13. Verify health

```bash
docker compose -f docker-compose.prod.yml logs --tail=50 web api
curl -f https://your-domain.com/health
curl -f https://your-domain.com/health/ready
curl -fI https://your-domain.com/ | head -1        # the SPA
curl -f https://your-domain.com/api/v1/meta       # the API through nginx
curl -fsI https://your-domain.com/.well-known/apple-app-site-association | grep -i content-type   # application/json, no redirect
curl -f https://your-domain.com/robots.txt                                    # names https://your-domain.com/sitemap.xml
curl -f https://your-domain.com/sitemap.xml                                   # the public pages, absolute https URLs
curl -sI https://your-domain.com/missing.xml | head -1                        # 404
curl -sI https://your-domain.com/dashboard | grep -i x-robots-tag             # noindex
curl -sI https://your-domain.com/api/v1/meta | grep -ci x-powered-by          # 0: the PHP version is not disclosed
docker compose -f docker-compose.prod.yml exec -T api php -v | head -1        # the PHP version actually running (worker: same image)
```

The API image follows the newest PHP 8.4 patch release (`php:8.4-fpm-alpine` in `docker/api/Dockerfile`): every deploy rebuilds it on the current base, so a PHP security release reaches production with the next deploy. Check the version with `php -v` above; the API does not send it in `X-Powered-By` (`expose_php=Off` in `docker/api/php.ini`).

For iOS Universal Links the `APPLE_APP_ID` in `.env` must be `<Team ID>.online.my-workouts.app` and must match the team that signs the app; the app's Associated Domains entitlement names `applinks:your-domain.com`. Apple's CDN caches the file for up to a day, so after changing it install a fresh build and allow time, or validate with Apple's AASA validator. Verification and reset emails keep linking to the web paths; the app intercepts them only where it is installed.

If `PRODUCTION_HEALTHCHECK_URL` and `PRODUCTION_READYCHECK_URL` are configured in GitHub, the deploy workflow performs these checks automatically and fails the release if they do not pass.

### Search Console

One-time setup, so Google can be told about the site and its indexing measured. It needs the owner's Google account.

1. In [Google Search Console](https://search.google.com/search-console) add a **Domain** property for `your-domain.com`
2. Add the TXT record it shows at the DNS provider (`@`, `google-site-verification=...`), wait for it to resolve (`dig +short TXT your-domain.com`), then press **Verify**. Keep the record: removing it unverifies the property
3. **Sitemaps**: submit `https://your-domain.com/sitemap.xml`; the status should become "Success" with the number of public pages
4. **URL Inspection**: inspect `https://your-domain.com/` and press **Request indexing**; "View crawled page" shows what Google downloaded
5. Optionally repeat steps 1–3 in [Bing Webmaster Tools](https://www.bing.com/webmasters), which can import the property from Search Console

After a release that adds public pages, resubmitting the sitemap is not required; Google rereads it. **Pages** in Search Console lists what is indexed and why other URLs are not (`noindex` pages appear as "Excluded by 'noindex' tag", which is expected).

## 14. Logs and Grafana

Every container writes to its stdout/stderr; Laravel gets there through php-fpm's `catch_workers_output` (`docker/api/zz-app.conf`), which requires `LOG_CHANNEL=stderr` in the droplet `.env` (a file channel such as `stack`/`single` stays inside the container and never reaches Grafana).

The `alloy` service (`docker/alloy/config.alloy`) tails the containers of the compose services `api`, `web` and `worker` and pushes them to Grafana Cloud Loki (`LOKI_URL`, `LOKI_USER_ID`, `LOKI_API_TOKEN`). Labels on every line:

| Label       | Value                                                                                                                          |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `app`       | `my-workouts`                                                                                                                  |
| `env`       | `production`                                                                                                                   |
| `service`   | `api` (Laravel: application log lines and request context), `worker` (queue), `web` (nginx errors only; access logging is off) |
| `logstream` | `stdout` or `stderr`                                                                                                           |

Since the SPA cutover, application logs carry `service="api"`; dashboards and alerts written against `service="web"` from the single-image era must be updated.

On the droplet:

```bash
docker compose -f docker-compose.prod.yml logs -f --tail=200 api      # Laravel (php-fpm)
docker compose -f docker-compose.prod.yml logs -f --tail=200 web      # nginx
docker compose -f docker-compose.prod.yml logs -f --tail=200 worker
docker compose -f docker-compose.prod.yml logs -f --tail=200 alloy    # shipping problems show up here
docker compose -f docker-compose.prod.yml logs -f --tail=200 mysql
```

### Go API

The Go API is not deployed yet (story 13 of the Go epic adds `api-go` and `worker-go` to the production compose file). Alloy already keeps their containers, so their lines will reach Loki with `service="api-go"` and `service="worker-go"`.

**Log fields.** Each line is one JSON object with `time`, `level` and `msg`, plus `service` and `version`. Lines written during a request also carry:

- `request_id` (the `X-Request-Id` header);
- `user_id` (after authentication);
- `trace_id` and `span_id`.

The access line (`msg="request"`) adds `method`, `path`, `route` (the mux pattern, `GET /api/v1/workouts/{workoutId}`), `status`, `bytes` and `duration_ms`. Business events use PHP's names and fields: `auth.login` and `user.registered` (with `client`), `auth.logout`, `auth.password_reset`, `user.email_verified`, `workout.started`, `workout.completed`, `program.enrolled`, and so on. A failed readiness check logs one warning per component.

**Metrics.** Both processes serve Prometheus metrics at `/metrics` on `ADMIN_ADDR` (`:9090` inside the container; in the dev stack, `localhost:9090` for `api-go` and `localhost:9091` for `worker-go`). nginx never proxies this port.

| Metric | Labels | From |
| --- | --- | --- |
| `http_server_request_duration_seconds` (histogram) | `method`, `route`, `status` | `api-go` |
| `http_server_requests_total` | `method`, `route`, `status` | `api-go` |
| `db_client_operation_duration_seconds` (histogram) | `pgx_operation_type`, `db_operation_name` | both |
| `jobs_active` | `kind` | `worker-go` |
| `jobs_failed_total` | `kind`, `reason` (`discarded`: last attempt failed; `cancelled`) | `worker-go` |

Go runtime metrics (`go_*`) and `target_info` (service, version, environment) come with them. `route` is the pattern's path, such as `/api/v1/workouts/{workoutId}`, or `unmatched` when no route matched.

The two SLIs:

```promql
# p99 latency over 5 minutes
histogram_quantile(0.99, sum by (le) (rate(http_server_request_duration_seconds_bucket[5m])))

# 5xx rate
sum(rate(http_server_requests_total{status=~"5.."}[5m])) / sum(rate(http_server_requests_total[5m]))
```

Nothing scrapes these in production yet. When the Go API is rolled out, add a `prometheus.scrape` block to `docker/alloy/config.alloy` for `api-go:9090` and `worker-go:9090`, with a `prometheus.remote_write` to Grafana Cloud.

**Traces.** Spans are named by route, and an incoming `traceparent` is continued. They are exported over OTLP/HTTP only when `OTEL_EXPORTER_OTLP_ENDPOINT` is set (the collector's base URL; traces go to `/v1/traces`). `LOG_LEVEL=debug` also logs every SQL statement, without its arguments. The decisions are in [ADR 0014](../services/api-go/docs/adr/0014-observability.md).

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
- **Deploy fails at "Syncing the catalog..."**: the job log lists the reason. `The catalog data is invalid` means a data file in the release is broken (the `CatalogDataIntegrityTest` should have caught it); fix it in a new release. The app itself is already running the new release, with the catalog unchanged.
- **Automatic deploy succeeds but app is unhealthy**: inspect the `Deploy Production` job logs first, then run the manual log and curl commands from steps 13 and 14 on the droplet.

### Health check returns 500 or readiness reports `jwt: down`

`/health` is a liveness probe and stays 200 even when the app is misconfigured; `/health/ready` reports each dependency (`database`, `jwt`) and answers 503 with `"status": "degraded"` when one is down. `jwt: down` means `JWT_PRIVATE_KEY` / `JWT_PUBLIC_KEY` are missing from the droplet `.env`. Fix:

```bash
docker compose -f docker-compose.prod.yml exec -T api php artisan jwt:keys   # prints the two lines
vim .env                                                                     # add them, plus JWT_COOKIE_SECURE=true and FRONTEND_URL
docker compose -f docker-compose.prod.yml up -d --force-recreate api worker  # containers read .env at creation
docker compose -f docker-compose.prod.yml exec -T api php artisan optimize
curl -f https://your-domain.com/health/ready
```

Then re-run the `deploy` workflow manually with the same `image_tag` so the release is verified end to end. Any other 500: `docker compose -f docker-compose.prod.yml logs --tail=100 api` shows the exception (requires `LOG_CHANNEL=stderr`).

### Rollback

The deploy workflow supports manual `workflow_dispatch` runs with an `image_tag` input.

Use rollback like this:

1. Open GitHub Actions.
2. Run the `deploy` workflow manually.
3. Provide a previously pushed release tag such as `v1.3.2` (both `api-v1.3.2` and `web-v1.3.2` must exist on Docker Hub; every release since the SPA cutover pushes both).
4. Leave `release_ref` blank unless you need to deploy an image tag that does not directly match the Git ref.
5. The workflow skips the build job and deploys that exact tag.

A rollback also re-applies that release's catalog data files: shipped records return to their older content. Records added by the newer release stay in the database, because the sync never deletes by omission. Database migrations are not rolled back.

**Rolling back past the source-text migrations.** Releases before the `2026_09_29_00000{1,2,3}` migrations read English catalog text from the `translations` table, where it no longer exists. Before deploying such a release, undo those migrations with the current release still running; this restores the English rows and drops the columns:

```bash
docker compose -f docker-compose.prod.yml exec -T api php artisan migrate:status
docker compose -f docker-compose.prod.yml exec -T api php artisan migrate:rollback --step=3 --force   # only while these are the three latest migrations
```

### SSL/HTTPS troubleshooting

- **"DNS problem: NXDOMAIN"** during certbot: DNS has not propagated yet. Verify with `dig +short your-domain.com` — it must return your droplet IP.
- **Rate limit errors**: Let's Encrypt has a limit of 5 duplicate certificates per week. Use `--staging` for testing to avoid hitting limits.
- **Certificate not found after certbot succeeds**: Make sure you restarted the web container (`docker compose ... restart web`) so `start.sh` can detect the cert files.
- **Mixed content warnings**: Ensure `APP_URL` in `.env` starts with `https://`.
- **Browser shows "Not Secure"**: The staging certificate is not trusted by browsers. Obtain the real certificate (without `--staging`).
- **Cert expired despite cron being installed**: an earlier version of `scripts/renew-certs.sh` ran `certbot renew --quiet`, which suppressed both error output and certbot's non-TTY random sleep of up to 8 minutes. Symptoms: `crontab -l` shows the entry, but `openssl s_client ... | openssl x509 -noout -dates` reports a past `notAfter`. Fix: confirm the droplet has the current `scripts/renew-certs.sh` installed at `/home/deploy/renew-certs.sh` (`diff scripts/renew-certs.sh ~/renew-certs.sh` from the repo dir) — a recent deploy installs it automatically; if missing, re-run `scripts/deploy-production.sh` or copy the file manually with `install -m 0755 scripts/renew-certs.sh ~/renew-certs.sh`. Then run `~/renew-certs.sh` manually once and inspect `~/renew-certs.log` to confirm renewal completes. If the cert is already expired, run `certbot ... renew --no-random-sleep-on-renew` directly (without the wrapper) to renew immediately, then reload nginx with `docker compose -f docker-compose.prod.yml exec -T web nginx -s reload`.

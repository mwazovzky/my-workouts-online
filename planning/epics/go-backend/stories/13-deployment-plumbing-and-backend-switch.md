# User Story: Deployment Plumbing and Backend Switch

Build and publish the Go image, add its services to the production compose file, and let nginx route the API to either backend through one `API_BACKEND` variable, shipped with `php` as the default.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: none
- Creates New Feature: No

## Problem Statement

Every Go story so far runs in dev and CI only. `deploy.yml` builds two images (`api`, `web`), `docker-compose.prod.yml` knows `web`, `api`, `worker`, `mysql`, `alloy`, and `docker/nginx/api-locations.conf` reaches the API over FastCGI with `SCRIPT_FILENAME` pointing at `public/index.php`, which cannot address a Go process. The epic promises plumbing, not a rollout: the image, the services, the nginx upstream and the deploy steps must exist and be exercised, while production keeps serving PHP until a later epic flips the switch. This story also retires the per-story contract scope so CI proves the whole surface.

## User-Visible Outcome

None. Production keeps answering from PHP. A developer can run `API_BACKEND=go docker compose up` and use the web SPA end to end against Go.

## Scope

- `docker/api-go/Dockerfile` (story 1) is built and pushed by `.github/workflows/deploy.yml` as `${IMAGE_NAME}:api-go-<tag>` and `api-go-sha-<12>`, with the same buildx cache pattern as the `api` target; `APP_VERSION` passed through `-ldflags`.
- `docker-compose.prod.yml`: `postgres:17` (volume `postgres_data`, healthcheck `pg_isready`, `mem_limit` 192m, `shared_buffers` sized in `docker/postgres/postgresql.conf`), `api-go` (image tag from `IMAGE_TAG`, `env_file .env`, `HTTP_ADDR=:8081`, `ADMIN_ADDR=:9090`, healthcheck `workouts healthcheck`, `mem_limit` 96m), `worker-go` (`workouts work`, `mem_limit` 64m); all three use `profiles: [go]` so `docker compose up` without the profile changes nothing for today's stack.
- nginx switch: `docker/nginx/api-locations-go.conf` with `upstream go_api { server ${GO_API_UPSTREAM} resolve; }` (resolver `127.0.0.11`, like the PHP upstream) and a `location` block matching the same regex as `api-locations.conf` (`^/(api|health|up)(/|$)|^/\.well-known/jwks\.json$`) using `proxy_pass http://go_api`, `proxy_set_header X-Request-Id $request_id` when absent, `X-Forwarded-For`, `X-Forwarded-Proto`, `Host`, `proxy_read_timeout 60s`; `docker/web/start.sh` and `docker/nginx/dev.conf.template` include `/etc/nginx/api-locations-${API_BACKEND}.conf` with `API_BACKEND` defaulting to `php`; `docker-compose.yml` mounts both snippets and passes `API_BACKEND`, `GO_API_UPSTREAM=api-go:8081`.
- `scripts/deploy-production.sh`: when `API_BACKEND=go`, pulls `api-go`, waits for Postgres, runs `docker compose run --rm api-go workouts migrate` then `workouts catalog sync` after the PHP steps, recreates `api-go` and `worker-go`, and health-checks `/health/ready` through nginx; when `php`, the script is byte-for-byte today's behaviour.
- `.env.example`: `API_BACKEND=php`, `GO_API_UPSTREAM`, `DATABASE_URL`, `LINK_SIGNING_KEY`, `HTTP_ADDR`, `ADMIN_ADDR` with comments; `docs/deployment.md` runbook section "Running the Go backend" and a rollback note (set `API_BACKEND=php`, recreate `web`).
- CI: delete `services/api-go/contract-scope.txt` and the `CONTRACT_INCLUDE` handling in `contract-go.yml` so Schemathesis runs the full contract against Go; mark `contract-go` as a required check on `main` (documented step in `docs/deployment.md` section 0, done by the maintainer).
- End-to-end check on the dev stack with `API_BACKEND=go`: register, verify (log mailer link), enroll, start and complete a workout, admin edit, all through the SPA at `http://localhost:8080`.

## Non-Goals

- flipping production to Go (separate epic; needs data migration and a rollback rehearsal)
- importing MySQL data into Postgres
- TLS or certbot changes (unchanged; the `web` image terminates TLS as today)
- removing the PHP services or images

## Acceptance Criteria

- `deploy.yml` on a tag pushes `api-go-<tag>` and `api-go-sha-<12>` next to the existing images
- `docker compose -f docker-compose.prod.yml config` validates with and without `--profile go`; without the profile the rendered services equal today's
- on the dev stack, `API_BACKEND=go docker compose up -d` serves the SPA and every `/api/v1` call hits Go (`X-Request-Id` echoed, `Server` header absent, logs in `api-go`); `API_BACKEND=php` restores PHP with no other change
- `curl -i http://localhost:8080/health/ready` through nginx returns the Go JSON when `API_BACKEND=go`
- the full `scripts/contract-test.sh http://localhost:8081` run is green and `contract-go.yml` runs it unscoped
- `scripts/deploy-production.sh` dry-run (`bash -n` plus a `DRY_RUN=1` echo mode added in this story) shows the Go steps only when `API_BACKEND=go`
- `docs/deployment.md` describes the switch and the rollback

## Initial Tasks

- [ ] `deploy.yml` build and push for the Go target
- [ ] prod compose services under the `go` profile; Postgres config
- [ ] `api-locations-go.conf`, `start.sh` and dev template include, compose wiring
- [ ] deploy script branches and `DRY_RUN`
- [ ] `.env.example`, docs runbook and rollback
- [ ] delete `contract-scope.txt`, unscope `contract-go.yml`, required check
- [ ] SPA end-to-end pass against Go on the dev stack

## Test Cases

- `docker compose config` snapshot test in CI for both profiles
- nginx config test (`nginx -t`) for both `API_BACKEND` values in the `web` image build
- full Schemathesis suite against Go in CI
- manual: SPA flows against `API_BACKEND=go`; rollback to `php` without data loss on the PHP side

## Docs To Update

- `docs/deployment.md` (images, prod compose profile, `API_BACKEND`, deploy steps, rollback, required checks)
- `docs/architecture.md` (hosting: two upstreams, one switch)
- `CLAUDE.md` (dev stack: `API_BACKEND`)

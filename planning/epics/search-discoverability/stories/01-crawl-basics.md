# User Story: Crawl Basics

Give search engines a map of the public site, an honest answer for missing files, and a clear signal about which pages not to index.

## Feature Mapping

- Primary Feature: Public Site
- Secondary Features: none
- Creates New Feature: Yes (`docs/features/public-site.md`, covering the landing, privacy and support pages and their search behaviour)

## Problem Statement

`robots.txt` allows everything but names no sitemap, and `/sitemap.xml` returns the SPA shell with status 200, because nginx's history-mode fallback serves `index.html` for every path. Any probed URL (`/sitemap.xml`, `/wp-login.php`) is a 200 page to a crawler. Signed-in pages carry no `noindex`, so a crawler that follows a link to `/dashboard` sees a login redirect it may index. The domain is not verified in Search Console, so nothing can be submitted or measured.

## User-Visible Outcome

Search engines find a sitemap that lists the public pages, get a 404 for files that do not exist, and are told not to index signed-in pages. The owner can verify the domain and submit the sitemap by following the deployment runbook.

## Scope

- `apps/web/public/robots.txt`: allow all, plus `Sitemap: https://my-workouts.online/sitemap.xml` (the host from the build's public URL, not hard-coded)
- `sitemap.xml` generated at build time with the public routes (`/`, `/privacy`, `/support`); later stories add their pages through the same generator
- `docker/nginx/spa-locations.conf`: a missing path with a file extension answers 404 instead of falling back to `index.html`
- nginx: `X-Robots-Tag: noindex` on the signed-in and auth route prefixes (`/dashboard`, `/workouts`, `/workout-templates`, `/programs`, `/profile`, `/admin`, `/about`, `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/confirm-password`)
- `NotFound.vue` sets `<meta name="robots" content="noindex">` while it is shown
- `docs/deployment.md`: a Search Console section (DNS TXT verification, sitemap submission, URL inspection)

## Non-Goals

- Head metadata (story 2) and prerendering (story 3)
- Public catalog pages (stories 4 and 5)
- Doing the Search Console verification itself; it needs the owner's Google account and is a launch task

## Acceptance Criteria

- `GET /robots.txt` names the sitemap
- `GET /sitemap.xml` returns `application/xml` listing exactly the public pages with absolute HTTPS URLs
- `GET /does-not-exist.xml` and `GET /wp-login.php` return 404; `GET /some/unknown/path` still renders the SPA's not-found page, with `noindex`
- `GET /dashboard` and the other private prefixes carry `X-Robots-Tag: noindex`; `/`, `/privacy`, `/support` and `/assets/*` do not
- `/.well-known/*`, `/api/*` and the SPA's existing routes behave as before
- The security headers still apply to every location

## Initial Tasks

- [ ] `robots.txt` with the sitemap line
- [ ] Sitemap generator in the web build (one list of public routes, reused by later stories)
- [ ] nginx: 404 for missing files with an extension; `X-Robots-Tag` on private prefixes, in both the dev and production configs
- [ ] `noindex` on `NotFound`
- [ ] Search Console section in `docs/deployment.md`
- [ ] `docs/features/public-site.md`; update `docs/pages-and-routes.md` and `docs/README.md`

## Test Cases

- Vitest: the sitemap generator outputs the expected URLs; `NotFound` sets and removes the robots meta
- A shell check against the dev stack (`curl -I`): status codes and `X-Robots-Tag` for `/`, `/dashboard`, `/sitemap.xml`, `/missing.xml`, `/unknown/path`, `/api/v1/meta`, `/.well-known/apple-app-site-association`
- The security headers are still present on a 404 and on a `noindex` response

## Docs To Update

- docs/features/public-site.md (new)
- docs/pages-and-routes.md
- docs/deployment.md
- docs/README.md (feature index)

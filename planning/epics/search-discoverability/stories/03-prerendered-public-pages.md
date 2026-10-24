# User Story: Prerendered Public Pages

Serve the landing, privacy and support pages as complete HTML, so a crawler reads them without running JavaScript.

## Feature Mapping

- Primary Feature: Public Site
- Secondary Features: Auth & Profile (the landing page's sign-up and log-in entry points)
- Creates New Feature: No

## Problem Statement

`Welcome.vue`, `Privacy.vue` and `Support.vue` exist, but only inside the client-rendered SPA: the HTML nginx serves for `/` has an empty `<div id="app">`. The landing page's heading, "Know what to train next", also never names the product or says it is a workout tracker, which are the words someone searching for it would type.

## User-Visible Outcome

Opening `/`, `/privacy` or `/support` shows the content immediately, before JavaScript loads, and the page then behaves as today. The landing page says plainly that My Workouts Online is a workout tracker with training programs, for the web and iPhone. The same pages exist in Russian under `/ru/`.

## Scope

- Choose and add the prerender tool (epic decision 1): a build script with `@vue/server-renderer`, or `vite-ssg`. The choice and the reason go in `docs/architecture.md`; new npm dependencies need approval
- The web build writes `index.html`, `privacy/index.html`, `support/index.html` and the `/ru/` versions into `dist/`, each with its own head metadata (story 2), `hreflang` links and `x-default`
- The client mounts over the prerendered markup without visible flicker; a signed-in visitor to `/` is still redirected to the dashboard
- nginx serves a prerendered file when one exists (`try_files $uri $uri/index.html ...`), and falls back to the SPA shell otherwise
- `/ru/` pages render with the Russian catalog; the guest locale switcher links between the two versions
- Landing copy: an `<h1>` that includes "My Workouts Online", one paragraph saying what the product does (log sets, follow programs, track progress, web and iPhone), the existing steps and highlights, and the App Store link once the app is live
- The sitemap (story 1) lists the `/ru/` pages

## Non-Goals

- Prerendering signed-in pages
- The catalog pages (stories 4 and 5)
- A redesign of the landing page beyond the copy changes above

## Acceptance Criteria

- `curl https://…/` returns HTML whose body contains the `<h1>` and the landing text; the same for `/privacy`, `/support` and the three `/ru/` pages
- Each page's HTML carries its own title, description, canonical URL and `hreflang` alternates
- Loading each page in a browser shows no hydration warnings in the console and no layout jump
- Signed-in users opening `/` reach the dashboard, as today
- The set of prerendered pages and the set of sitemap URLs are identical: every prerendered page is in the sitemap and every sitemap URL has a prerendered page
- `npm run build` stays under 60 seconds in CI
- `docker/web` image build and the dev stack (`node` with Vite) both work; dev keeps hot reload

## Initial Tasks

- [ ] Spike both tools on `Welcome.vue`; record the choice
- [ ] Prerender step in the web build; route list shared with the sitemap generator
- [ ] Russian routes under `/ru/` for the public pages; locale switcher links
- [ ] nginx `try_files` for prerendered files, dev and production
- [ ] Landing copy, both languages
- [ ] Update `docker/web/Dockerfile` if the build needs new steps

## Test Cases

- Vitest (or a build test): the generated HTML for each route contains the expected heading, title, canonical and `hreflang` links
- Build test: the prerendered files in `dist/` and the URLs in `dist/sitemap.xml` match exactly, in both directions; later stories' pages are covered by the same test
- Vitest: `Welcome`, `Privacy` and `Support` render on the server without touching `window` or `localStorage`
- The existing guard tests still pass: a signed-in user on `/` is redirected
- Manual: Google's URL Inspection "View crawled page" (or the Rich Results Test) shows the landing text

## Docs To Update

- docs/features/public-site.md
- docs/architecture.md (prerendering)
- docs/pages-and-routes.md (`/ru/` routes)
- docs/deployment.md if the image build changes

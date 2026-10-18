# Epic: Search Discoverability

**Status: planned.**

Make My Workouts Online findable in Google: first under its own name, then for the training questions its catalog already answers ("dumbbell romanian deadlift how to", "full body gym program 3 days").

## Why This Epic Exists

A search for the product's name does not return the site. Checked on 2026-10-18:

- **The HTML a crawler downloads is an empty shell.** `GET https://my-workouts.online/` returns an 844-byte `index.html` with `<title>My Workouts</title>`, no description and an empty `<div id="app">`. The landing page (`Welcome.vue`, titled "My Workouts Online"), the privacy policy and the support page exist only after JavaScript runs. Google can render JavaScript, but it does so later and less reliably than it reads HTML, and a page with no text in its HTML is a weak candidate for the index.
- **There is nothing to crawl beyond `/`.** No sitemap: `/sitemap.xml` answers 200 with the SPA shell, because nginx's history-mode fallback (`docker/nginx/spa-locations.conf`) serves `index.html` for every path. Every mistyped or probed URL is a 200 "soft 404" to a crawler. `robots.txt` allows everything but names no sitemap.
- **No metadata.** No `meta description`, canonical URL, Open Graph tags or structured data, so search results and shared links have nothing to show.
- **No external signals.** Nothing found links to the domain, and the domain is not verified in Google Search Console, so there is no way to request indexing or see why pages are skipped.
- **The name is generic.** "My Workouts" is also the name of several App Store and Microsoft Store apps. A new domain with thin pages loses that query to them.

Meanwhile the product already holds content people search for: 15 programs and 137 exercises with how-to descriptions in English and Russian, in `catalog/`. None of it is reachable without an account.

## Decisions

| # | Decision | Choice |
| --- | --- | --- |
| 1 | Rendering | Build-time prerendering of public routes to static HTML, served by the existing nginx. No server-side rendering at request time and no Node process in production. The signed-in app stays a client-rendered SPA. The tool is chosen in story 3 between a small build script using `@vue/server-renderer` on the same components and `vite-ssg`; new npm dependencies need approval. |
| 2 | Public catalog source | The public program and exercise pages are generated at build time from the shared YAML in `catalog/`, the same files both backends sync from. No new public API endpoint. Admin-created records (no slug) are not published. |
| 3 | Public URLs | Slugs from `catalog/`, under a prefix that cannot collide with the app's numeric routes: `/catalog/programs/{slug}` and `/catalog/exercises/{slug}`, named "program catalog" and "exercise catalog" on the pages, matching the code's naming (`catalog/`, the catalog module). The app's `/programs/{id}` stays signed-in only. |
| 4 | Languages | English at the unprefixed path, Russian under `/ru/` (`/ru/catalog/exercises/{slug}`), linked both ways with `hreflang` and an `x-default`. The app itself keeps its client-side locale and its URLs. |
| 5 | Private pages | Signed-in routes (`/dashboard`, `/workouts`, `/profile`, `/admin`, the auth flows) answer with `X-Robots-Tag: noindex` from nginx. They are not disallowed in `robots.txt`, because a crawler that may not fetch a page never sees its `noindex`. |
| 6 | Missing files | nginx answers 404 for a missing path with a file extension (`/sitemap.xml` before story 1, `/wp-login.php`) instead of the SPA shell. Unknown extensionless paths still reach the SPA, whose `NotFound` page sets `noindex`. |
| 7 | Brand | The product name in titles, headings and structured data is "My Workouts Online", matching the domain, so the name query is distinct from the other "My Workouts" apps. |
| 8 | Measurement | Google Search Console is the source of truth: indexed pages, impressions, clicks and queries. No new analytics script on the site. |

## Scope

- nginx: `X-Robots-Tag` on private routes, 404 for missing files
- `apps/web`: `robots.txt`, a generated `sitemap.xml`, head metadata, prerendered public pages, the public catalog pages and their build step
- `docs/`: a Public Site feature page, the routes inventory and the deployment runbook (Search Console)

## Non-Goals

- Server-side rendering of the signed-in app
- Paid search, ads or an analytics script
- A blog or written articles beyond the catalog content
- Publishing user-generated content (workouts, admin-created exercises)
- Exercise images or video (a separate epic)

## Story Order

| # | Story | Outcome |
| --- | --- | --- |
| 1 | [Crawl basics](stories/01-crawl-basics.md) | `robots.txt` with a sitemap, a real `sitemap.xml`, 404 for missing files, `noindex` on private routes, the domain verified in Search Console |
| 2 | [Head metadata](stories/02-head-metadata.md) | Description, canonical, Open Graph and JSON-LD in the HTML; per-route titles |
| 3 | [Prerendered public pages](stories/03-prerendered-public-pages.md) | `/`, `/privacy` and `/support` (and their `/ru/` versions) are complete HTML before JavaScript runs; the landing page names the product |
| 4 | [Public program pages](stories/04-public-program-pages.md) | `/catalog/programs` and one page per shipped program, in English and Russian |
| 5 | [Public exercise catalog](stories/05-public-exercise-catalog.md) | `/catalog/exercises` and one page per shipped exercise with its how-to description, in English and Russian |

## Sequencing Rationale

- Story 1 first: it is small, needs no new tooling, and Search Console needs a sitemap and a verified domain before any later change can be measured.
- Story 2 before 3: the head tags live in `index.html` and the router, and the prerender step in story 3 then writes them into each page's HTML.
- Story 3 introduces the prerender tooling on three pages that already exist, so the tool is proven before it generates hundreds of pages.
- Stories 4 and 5 reuse that tooling. Programs come first because they are fewer and link to the exercises; the exercise catalog is where most search traffic is expected.

## Launch Tasks (outside the stories)

Not code, but needed for the domain to be found:

- Verify `my-workouts.online` in Google Search Console with a DNS TXT record, submit `https://my-workouts.online/sitemap.xml`, and request indexing of `/` (owner; after story 1).
- Set the App Store listing's marketing and support URLs to the site.
- Link the site from the repository README.
- Announce the launch where lifters look for tools (Product Hunt, r/fitness, r/weightroom).

## Success Metrics

Primary signals:

- the site is the first result for "my workouts online" within eight weeks of story 3
- every URL in the sitemap is reported as indexed in Search Console
- organic clicks to `/catalog/*` pages grow month over month after stories 4 and 5

Secondary signals:

- sign-ups whose first page was a public page
- Russian-language impressions for `/ru/` pages

## Risks

- **Hydration mismatches.** A prerendered page that differs from what the client renders flickers or logs warnings. Mitigation: render the public pages from the same components, and test the generated HTML.
- **Thin or duplicate content.** One page per exercise with two sentences of text may be judged thin. Mitigation: each page also lists equipment, muscle categories, the programs and templates that use it, and related exercises.
- **Build time and image size** grow with every catalog record. 2 × (15 + 137) pages is small; the risk is low but measured in story 5.
- **Catalog drift.** The public pages are built from `catalog/` at image build time, so they change on deploy, as the databases do through `catalog:sync`. A record removed through `retired.yaml` disappears from the public catalog and the sitemap in the same deploy.
- **Indexing takes time.** A new domain can take weeks to appear even when everything is right; the success metric allows for it.

## Done Criteria

This epic is done when:

- every public page is complete HTML with metadata before JavaScript runs, in English and Russian
- the sitemap lists exactly the public pages, and Search Console reports them indexed
- private pages are `noindex` and missing files are 404
- `docs/features/public-site.md`, `docs/pages-and-routes.md` and `docs/deployment.md` describe the shipped behaviour

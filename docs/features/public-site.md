# Public Site

The pages anyone can open without an account (landing, privacy, support, the program and exercise catalogs) and how search engines crawl the site.

## Current Behavior

1. **Landing** — `/` shows the product to signed-out visitors: a heading that names My Workouts Online, what it does, and sign-up and log-in entry points; signed-in visitors are redirected to the dashboard
2. **Privacy and Support** — `/privacy` and `/support` are reachable signed in or out and are linked from the layout footers, the landing page and the iOS Profile tab
3. **Program catalog** — `/catalog/programs` lists every shipped program with its description and number of workouts a week; `/catalog/programs/{slug}` shows one program: description, workouts a week, the equipment it needs, the weekly schedule (each day linking to its workout below), and each workout with its exercises and planned sets ("3 × 10 reps", "45 min (zone 2)"). Exercise names link to their exercise pages. A guest is invited to create an account; a signed-in user is sent to Programs in the app. Linked from the landing page and the footer
4. **Exercise catalog** — `/catalog/exercises` lists every shipped exercise grouped by its main category, with its equipment; once the page has loaded, a filter by name and equipment appears (without JavaScript the full list stays readable). `/catalog/exercises/{slug}` shows how to do the exercise (its description), its equipment, categories and whether it is measured by repetitions or time, the programs and workouts that use it, related exercises with the same main category, and a sign-up box for guests. Linked from the landing page, the footer and every program page
5. **English and Russian** — each public page also exists in Russian under `/ru` (`/ru`, `/ru/privacy`, `/ru/support`, `/ru/catalog/programs/…`, `/ru/catalog/exercises/…`). The guest locale switcher moves between the two versions, and the footer and logo links lead to the version in the current language
6. **Complete HTML** — every public page is prerendered: the HTML a crawler or browser downloads already holds the page's text, title and metadata, and the app takes over in place once it has loaded
7. **Crawling** — `/robots.txt` allows every crawler and names `/sitemap.xml`, which lists the public pages as absolute URLs
8. **Not indexed** — signed-in and auth pages answer with `X-Robots-Tag: noindex`; the SPA's not-found page adds `<meta name="robots" content="noindex">` while it is shown
9. **Missing files** — in production a missing path with a file extension (`/missing.xml`, `/wp-login.php`, `/assets/missing.js`) answers 404; any other unknown path renders the SPA's not-found page
10. **Search results and link previews** — `index.html` carries a default title ("Workout Tracker & Training Programs – My Workouts Online"), description, Open Graph and Twitter card tags with a 1200 × 630 image (`public/og-image.png`), and one JSON-LD block describing the `WebSite` and its publishing `Organization` (name, logo, support email)
11. **Per-page metadata** — every public page sets its own title, description, canonical URL, `hreflang` links (`en`, `ru`, `x-default` → English) and `og:title` / `og:description` / `og:url` / `og:locale` in the page's language; a program page adds a JSON-LD `CreativeWork` and an exercise page a `WebPage` about the exercise (name, description, URL, language, the site it belongs to). An exercise page's title is "{Exercise}: how to do it" and its description is the exercise's. Leaving a page restores the defaults and removes what it added. Every page title ends in "– My Workouts Online"

## Business Rules

- The public pages are the fixed `PUBLIC_PAGES` in `apps/web/src/lib/crawl.js` plus the program and exercise catalogs (`catalogPaths`), each in every language (`localizedPath`: English unprefixed, Russian under `/ru`). The build prerenders exactly those, the sitemap lists them, and the build fails if the two differ, or if two pages in one language share a title or a description
- The catalogs are built from the shipped YAML in `catalog/` (`programs.yaml`, `workout-templates/`, `exercises/`, `equipment.yaml`, `categories.yaml`, `retired.yaml`) at build time, not from the API: every program and exercise in the files gets a page, those listed in `retired.yaml` get none, and admin-created programs and exercises are never published. A missing Russian name or description falls back to English. The web build fails with the record named when a program or exercise refers to a workout template, exercise, equipment item or category that does not exist or is retired, or a field a page shows is missing
- Program pages show no weight: templates plan sets and repetitions (or durations and heart-rate zones), and each user picks their own weight
- Language of a public page: for a guest the URL decides, without recording a choice; a guest who chose a language before (switcher or a past sign-in) and a signed-in user are sent to the version in their language
- A prerendered page is reused in place only for a guest who stays on it; a signed-in visitor, or one sent to another version, gets a fresh render with the default head
- `robots.txt` and `sitemap.xml` are generated by the web build (and served by the Vite dev server) for the origin in `VITE_PUBLIC_URL`; the production image cannot be built without it (`PUBLIC_URL` build argument, from the `PRODUCTION_PUBLIC_URL` GitHub variable). Locally they point at `http://localhost:8080`
- Private pages are marked `noindex` instead of disallowed in `robots.txt`: a crawler has to fetch a page to see its `noindex`
- The `noindex` prefixes live in `docker/nginx/robots-map.conf`: `/dashboard`, `/about`, `/programs`, `/workout-templates`, `/workouts`, `/profile`, `/admin`, `/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`, `/confirm-password`. A Vitest test fails if a router path that is not a public page is not covered; `/catalog/…` is not under any of them
- The security headers apply to every response, including 404s and `noindex` pages
- Absolute URLs in `index.html` are written as `__SITE_URL__` and replaced by the build with the public origin; at runtime `usePageMeta` uses `VITE_PUBLIC_URL`, or the current origin when it is unset
- The shell `index.html` has no canonical URL or `og:url`: as `spa.html` it is served for every other path, so a fixed value would point those pages at `/`. Each public page gets its own from `usePageMeta`, written into its prerendered HTML
- The JSON-LD is a data block, not a script, so the Content-Security-Policy (`script-src 'self'`) does not apply to it

## Known Limitations

- Only the public pages are prerendered; every other route is the client-rendered app shell (`spa.html`)
- A signed-in visitor's call to action opens Programs rather than the program itself: the API does not expose catalog slugs, so the page cannot know the program's id
- A program or exercise slug the catalog does not have shows the not-found page (`noindex`), with status 200 like every other unknown path
- A catalog change reaches the public pages with the next web image build, like the backends' `catalog:sync` on deploy
- The catalog data (every exercise description in both languages, about 29 KB compressed) is one file shared by all catalog pages, so a program page downloads the exercise descriptions too
- Exercise pages have no images or video
- The exercise catalog adds 276 pages: about 1 second of build time and 5 MB to the web image (October 2026)
- A prerendered page is shown as static HTML until the session check finishes; links on it work as ordinary page loads in that moment
- The Russian versions repeat the English layout and images; only the text differs
- The dev stack does not prerender; the pages render in the browser as before
- The dev stack serves Vite's files with extensions, so the 404 for missing files exists only in the production image
- The domain is verified in Search Console by hand ([deployment](../deployment.md#search-console))

## Surface Area

- Pages: `Welcome` (`welcome`, `welcome.ru`), `Privacy` (`privacy`, `privacy.ru`), `Support` (`support`, `support.ru`), `Catalog/ProgramIndex` (`catalog.programs`, `catalog.programs.ru`), `Catalog/ProgramShow` (`catalog.programs.show`, `catalog.programs.show.ru`), `Catalog/ExerciseIndex` (`catalog.exercises`, `catalog.exercises.ru`), `Catalog/ExerciseShow` (`catalog.exercises.show`, `catalog.exercises.show.ru`), `NotFound` (`not-found`)
- Build: `src/lib/catalogFiles.js` (reads `catalog/`, `CATALOG_PATH` overrides; the `virtual:public-catalog` module), `src/lib/publicCatalog.js` (the public catalog and its checks)
- Files: `/robots.txt`, `/sitemap.xml`
- See [Pages & Routes](../pages-and-routes.md)

## Key Decisions

- Exercise pages carry a `WebPage` whose `about` is the exercise, not an `ExercisePlan` or `HowTo`: Google shows no rich result for either here, and `HowTo` results are retired
- Program pages carry a `CreativeWork`, not a `Course` or `ExercisePlan`: Google has no rich result for workout programs, and the `Course` result's requirements (provider, offers) do not fit
- The structured data describes a `WebSite` and an `Organization` rather than a `SoftwareApplication`: Google's software-app result needs ratings or reviews the site does not have, while `WebSite.name` is what Google uses for the site name shown in results
- The Open Graph image `public/og-image.png` is rendered from `apps/web/src/assets/og-image.svg` (the logo mark and the Figtree font, which the SVG names but does not embed); when the name or tagline changes, edit the SVG and render it again at 1200 × 630 with the Figtree TTF files available, for example with resvg

## Related

- [Pages & Routes](../pages-and-routes.md)
- [Deployment](../deployment.md#search-console)
- [Auth & Profile](auth-and-profile.md)

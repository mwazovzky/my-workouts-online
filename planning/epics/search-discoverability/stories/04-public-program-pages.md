# User Story: Public Program Pages

Publish the shipped training programs as public pages, so people searching for a program can find one and see what it involves before signing up.

## Feature Mapping

- Primary Feature: Public Site
- Secondary Features: Programs (a signed-in visitor can open the program in the app)
- Creates New Feature: No

## Problem Statement

The 15 shipped programs (Gym Full Body A / B, Push / Pull / Legs, Strength 5x5, the bodyweight and cycling programs) are only visible after signing in. Someone searching "3 day full body gym program" or "kettlebell beginner program" cannot reach them, and a visitor to the landing page cannot see what the app offers before creating an account.

## User-Visible Outcome

`/catalog/programs` lists every shipped program. Each program has a page at `/catalog/programs/{slug}` with its description, the weekly schedule, and each workout's exercises with their sets and repetitions, linking to the exercise pages (story 5). A call to action leads to sign-up, or into the app for a signed-in visitor. Everything is also in Russian under `/ru/`.

## Scope

- A build step that reads `catalog/programs.yaml`, `catalog/workout-templates/*.yaml`, `catalog/exercises/*.yaml`, `catalog/equipment.yaml` and `catalog/categories.yaml` and prerenders the index and one page per program, in both languages (epic decisions 2–4)
- Page content: name, description, schedule by weekday, each template with its exercises and planned sets, equipment needed, and the call to action
- Head metadata per page (story 2's composable): title "{Program} – My Workouts Online", description from the program, canonical, `hreflang`, and JSON-LD describing the page as a `CreativeWork` (no markup for a rich-result type Google does not support for this content)
- Sitemap entries for every page
- `docker/web/Dockerfile`: the web build stage gets `catalog/` in its context
- A link to `/catalog/programs` from the landing page and the footer

## Non-Goals

- Enrolling from the public page without signing in
- Programs created by admins in the app (no slug)
- Per-user data such as enrollment state

## Acceptance Criteria

- Every program in `catalog/programs.yaml` has an English and a Russian page; no other program has one
- Each page's HTML contains the program name, description, schedule and exercises without JavaScript
- Exercise names link to `/catalog/exercises/{slug}` (story 5; until it ships, the names are plain text)
- The call to action opens `/register` for a guest and the program in the app for a signed-in user
- A program removed through `retired.yaml` has no page and no sitemap entry after the next build
- The build fails with a clear message if a program references a template or exercise slug that does not exist

## Initial Tasks

- [ ] Catalog reader for the web build (YAML, localized fields, the activity shorthand `sets: N, effort: M`)
- [ ] Program index and program page components, server-renderable
- [ ] Prerender and sitemap wiring
- [ ] Dockerfile context
- [ ] Strings in both locale catalogs; landing and footer links

## Test Cases

- Vitest: the catalog reader expands the activity shorthand, resolves localized names with an English fallback, and rejects unknown references
- Vitest: a program page renders name, schedule, exercises and the call to action for both locales
- Build test: the page count equals the number of programs × 2; story 3's sitemap check (prerendered pages and sitemap URLs match exactly) passes

## Docs To Update

- docs/features/public-site.md
- docs/pages-and-routes.md
- catalog/README.md (the web build reads the catalog too)

# User Story: Public Exercise Catalog

Publish every shipped exercise with its how-to description, so people searching how to do an exercise land on the site.

## Feature Mapping

- Primary Feature: Public Site
- Secondary Features: Workout Logging (the same descriptions appear in the app's exercise info)
- Creates New Feature: No

## Problem Statement

`catalog/exercises/*.yaml` holds 137 exercises with a short how-to description in English and Russian, the same text the app shows behind its exercise info button. Exercise technique is one of the most common kinds of fitness search, and this content is the site's best chance of being found by people who do not know the product yet. Today none of it is public.

## User-Visible Outcome

`/catalog/exercises` lists every shipped exercise, grouped by muscle category, with a client-side filter by name and equipment. Each exercise has a page at `/catalog/exercises/{slug}` with the description, equipment, muscle categories, whether it is counted in repetitions or time, the programs and workouts that use it, and related exercises in the same category. Everything is also in Russian under `/ru/`.

## Scope

- Prerender the index and one page per exercise in both languages from `catalog/`, reusing story 4's catalog reader and build step
- Page content as above; "used in" lists the programs and templates that reference the exercise, linking to story 4's pages
- Head metadata per page: title "{Exercise} – How to Do It | My Workouts Online", the description as the meta description, canonical, `hreflang`, and JSON-LD describing the page as a `WebPage` about the exercise (no markup for a rich-result type Google does not support for this content)
- Sitemap entries for every page
- Story 4's program pages link exercise names to these pages
- A link to `/catalog/exercises` from the landing page and the footer
- Measure the build: time and `dist/` size before and after

## Non-Goals

- Images or video (a separate epic)
- Exercises created by admins in the app, or retired exercises
- User comments, ratings or personal records

## Acceptance Criteria

- Every active exercise in `catalog/exercises/*.yaml` has an English and a Russian page; retired exercises have none
- Each page's HTML contains the name, description, equipment and categories without JavaScript
- An exercise without a Russian description falls back to English on the `/ru/` page and is still listed
- The index filter works after JavaScript loads; without JavaScript the full grouped list is still readable
- The web build adds less than 30 seconds and the image less than 10 MB
- No two pages share a title or description

## Initial Tasks

- [ ] Exercise index and exercise page components, server-renderable
- [ ] "Used in" and "related exercises" from the catalog reader
- [ ] Prerender and sitemap wiring
- [ ] Link exercise names from the program pages
- [ ] Strings in both locale catalogs; landing and footer links

## Test Cases

- Vitest: an exercise page renders name, description, equipment, categories, "used in" and related exercises for both locales
- Vitest: the English fallback for a missing Russian description
- Build test: the page count equals active exercises × 2; titles and descriptions are unique; story 3's sitemap check passes

## Docs To Update

- docs/features/public-site.md
- docs/pages-and-routes.md

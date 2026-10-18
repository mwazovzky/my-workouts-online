# User Story: Head Metadata

Tell search engines and link previews what each public page is.

## Feature Mapping

- Primary Feature: Public Site
- Secondary Features: none
- Creates New Feature: No

## Problem Statement

`index.html` has only `<title>My Workouts</title>`: no description, canonical URL, Open Graph tags or structured data. A search result has no snippet to show, a shared link has no preview, and the generic title matches other apps named "My Workouts". `usePageTitle` sets `document.title` but nothing else.

## User-Visible Outcome

Search results show "My Workouts Online" with a short description of the product, and a link shared in a chat shows a title, description and image.

## Scope

- `index.html` defaults: title "My Workouts Online – Workout Tracker & Training Programs", `meta description`, `og:*` and `twitter:card` tags, an Open Graph image in `public/`
- JSON-LD in `index.html`: `WebApplication` (name, URL, application category, operating systems web and iOS, free offer) and `Organization` with the support email
- A `usePageMeta` composable (or an extension of `usePageTitle`) that sets title, description, canonical URL and `og:*` per route, and resets them on navigation
- Title and description for `/`, `/privacy`, `/support` in English and Russian (`src/i18n/locales`)

## Non-Goals

- Prerendering the tags into per-page HTML (story 3)
- Metadata for the catalog pages (stories 4 and 5 use the composable)
- An analytics or tag-manager script

## Acceptance Criteria

- The HTML of every page contains the default title, description, canonical URL, Open Graph tags and one valid JSON-LD block
- After navigation, the document's title, description, canonical and `og:*` match the route
- The JSON-LD passes the Schema.org validator and Google's Rich Results Test without errors
- The Content-Security-Policy is unchanged: the JSON-LD block is data, not script, and is allowed by `script-src 'self'`
- The Open Graph image is at most 300 KB and 1200 × 630 px

## Initial Tasks

- [ ] Default tags and JSON-LD in `index.html`
- [ ] Open Graph image
- [ ] Per-route metadata composable; apply it on `Welcome`, `Privacy`, `Support`
- [ ] Strings in both locale catalogs

## Test Cases

- Vitest: the composable sets and replaces title, description, canonical and `og:*`; a route without metadata falls back to the defaults
- Vitest: the JSON-LD in `index.html` parses and has the expected `@type` and `name`
- Manual: the Rich Results Test and a link preview (for example in Telegram or Slack) on a preview deploy

## Docs To Update

- docs/features/public-site.md

# User Story: SPA Page Migration

Move every page, layout, and component into `apps/web` and replace all Inertia and Ziggy usage with vue-router.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Programs, Workout Logging, Admin Content Management, Auth & Profile
- Creates New Feature: No

## Problem Statement

About 53 files import `@inertiajs/vue3` and about 37 call Ziggy `route()`. Pages cannot render outside Inertia.

## User-Visible Outcome

Every existing page works identically in the SPA.

## Scope

- move `resources/js/Pages`, `Components`, `Layouts`, `composables`, `utils`, `lib`, and CSS
- named routes in `src/router/routes.js` mirroring the current URL scheme
- `router.visit` to `router.push`; `Link` to `RouterLink` inside `NavLink`, `DropdownLink`, `ResponsiveNavLink`; `Head` to route meta titles; `usePage().props` to stores; `route()` to named routes; `route().current()` to `useRoute().name`
- `WorkoutEdit` unsaved-changes guard via `onBeforeRouteLeave`
- shadcn `Button as="a"` links render through `:as="RouterLink"` so `:to` navigates client-side
- pages are lazy-loaded through an `import.meta.glob` map (Vite cannot resolve nested paths in variable dynamic imports)
- `apps/web/src/test/utils.js` provides `RouterLinkStub` and `withPinia()` for component tests
- `GuestLocaleSwitcher` writes client state; signed-in switch calls `PATCH /profile/locale` and updates the store
- `DeleteUserForm` success logs out and navigates home
- logout via the auth store instead of a POST form link
- vitest mocks switched from Inertia/Ziggy to vue-router test helpers

## Non-Goals

- visual changes
- the six auth pages (story 8)

## Acceptance Criteria

- no import from `@inertiajs/vue3` remains in `apps/web`
- no global `route()` remains
- every page in `docs/pages-and-routes.md` renders and functions in the SPA
- frontend test suite passes in `apps/web`

## Initial Tasks

- route table
- mechanical replacements per file group
- guard and locale switcher rewrites
- test updates

## Test Cases

- existing component tests pass with router mocks
- `WorkoutEdit` leave guard prompts when dirty
- navigation active state matches route names

## Docs To Update

- docs/pages-and-routes.md (page column becomes SPA routes)

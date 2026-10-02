# User Story: Polish, Accessibility and Current-State Docs

Make the app feel finished and bring `docs/` up to date before release.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Workout Logging, Programs, Auth & Profile
- Creates New Feature: No

## Problem Statement

Feature stories ship behaviour, not finish. Before TestFlight the app needs an icon, a launch screen, consistent states, accessibility, and documentation that describes the iOS client as current state.

## User-Visible Outcome

Dynamic Type works everywhere, VoiceOver reads set controls meaningfully, completing a set gives haptic feedback, every screen has loading, empty and error states, and the app has an icon and launch screen.

## Scope

- Dynamic Type audit (no truncation at accessibility sizes; set rows wrap)
- VoiceOver labels and hints for set fields, done toggles, add and remove buttons, progress bar
- haptics on set completion and workout completion
- loading, empty and error state audit per screen
- app icon, accent colour, launch screen
- keyboard handling in the editor (numeric pads, next-field)
- docs: iOS client section in `docs/architecture.md` (stack, session, client generation, draft store, directory guide), iOS screens table in `docs/pages-and-routes.md`, iOS section in `docs/testing.md`, feature docs reviewed for parity notes; decide whether a dedicated `docs/ios.md` earns its place and, if so, add its role to `docs/documentation-standard.md`

## Non-Goals

- iPad layout, widgets, App Store metadata (story 14)

## Acceptance Criteria

- the editor is usable at the largest accessibility text size
- VoiceOver can complete a set and save without sighted help
- every screen handles empty and error states without blank areas
- `docs/` describes the iOS client; `CLAUDE.md` and root `README.md` are current

## Initial Tasks

- [ ] accessibility audit and fixes
- [ ] haptics, keyboard handling
- [ ] icon and launch screen
- [ ] docs

## Test Cases

- Accessibility Inspector audit with no critical issues on the editor and auth screens
- UI smoke suite still passes at an accessibility text size

## Docs To Update

- `docs/architecture.md`, `docs/pages-and-routes.md`, `docs/testing.md`, `docs/README.md`, `CLAUDE.md`, `README.md`
- `docs/documentation-standard.md` only if a new doc is introduced

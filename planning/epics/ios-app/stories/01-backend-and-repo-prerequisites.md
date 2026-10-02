# User Story: Backend and Repo Prerequisites

Make the contract generator-compatible, serve the Apple App Site Association file, and keep the future `apps/ios` tree out of Docker builds. No Swift code yet.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Auth & Profile (Universal Links hosting), Workout Logging (contract shapes only)
- Creates New Feature: No

## Problem Statement

Three things block an iOS client before any Swift is written. `swift-openapi-generator` silently drops properties declared as `anyOf`/`oneOf` of a `$ref` and `null` and does not handle `const`; `contracts/openapi.yaml` uses the first shape on `Activity.exercise_effort_type`, `Activity.exercise_difficulty_unit`, `Workout.workout_template` and `Dashboard.in_progress_workout`, and `const` on `AuthResponse.token_type` and `ValidationError.code`. nginx routes everything outside `/api`, `/health`, `/up` and `/.well-known/jwks.json` to the SPA, so `/.well-known/apple-app-site-association` would return `index.html`. `.dockerignore` does not exclude `apps/ios`, and both images build with context `.`.

## User-Visible Outcome

None. The web SPA and the API behave identically.

## Scope

- Reshape the four nullable `$ref` properties to a generator-compatible form. Preferred: a `$ref` with a sibling `type: [..., 'null']` (legal in OpenAPI 3.1). Fallback for the two enums: inline `type: [string, 'null']` with the enum values plus `null`; for the two objects: a named nullable wrapper schema. Replace the two `const`s with single-value `enum`s.
- Spike: run the generator once against the edited spec in a throwaway package outside the repo and confirm zero "skipping" diagnostics for non-admin schemas, before story 2 commits to it.
- Regenerate `apps/web/src/api/types.d.ts` with `npm run api:types` and confirm the diff is representation-only.
- `docker/nginx/well-known-locations.conf`: an exact-match `location = /.well-known/apple-app-site-association` with `default_type application/json`, `expires 1h`, `try_files $uri =404`, included by `default.conf.template`, `default-ssl.conf.template` and `dev.conf.template` next to the existing snippets.
- `docker/web/apple-app-site-association.template` rendered by `docker/web/start.sh` into the nginx html root from `APPLE_APP_ID`; when the variable is empty the rendered file is a valid `{"applinks":{"details":[]}}` so dev and pre-release production stay correct. Paths: `/verify-email/*`, `/reset-password/*`.
- `APPLE_APP_ID` added to `.env.example`, `docker-compose.prod.yml` (`web` service), and the deploy runbook.
- `.dockerignore`: add `apps/ios`. `.gitignore`: add a `# iOS app (apps/ios)` section with `/apps/ios/*.xcodeproj`, `xcuserdata/`, `DerivedData/`, `*.xcresult`, `/apps/ios/Config/Local.xcconfig`, `.build/`, `.swiftpm/`.
- Record the bundle identifier and Apple Team ID in the epic README decisions table once the Apple Developer account exists (may be a placeholder until story 14).

## Non-Goals

- any Swift code or Xcode project
- PHP changes; responses do not change
- the Associated Domains entitlement on the app side (story 6)

## Acceptance Criteria

- Redocly lint passes on the edited contract
- `scripts/contract-test.sh` passes against the dev stack
- `npm run api:types` output differs from the previous file only in how nullability is expressed; no field added or removed
- the generator spike compiles the four reshaped properties as optionals with no skipped-property warning
- `curl -i http://localhost:8080/.well-known/apple-app-site-association` on the dev stack returns 200 with `Content-Type: application/json` and the empty applinks body; `curl -i http://localhost:8080/some/spa/path` still returns the SPA
- `docker build` context no longer includes `apps/ios` (verified by adding a dummy file and inspecting the build output)

## Initial Tasks

- [ ] edit the six schema sites in `contracts/openapi.yaml`
- [ ] generator spike outside the repo; note the exact generator version that worked
- [ ] regenerate web types and review the diff
- [ ] nginx snippet, template includes, AASA template, `start.sh` rendering
- [ ] env var plumbing and `.env.example`
- [ ] `.dockerignore` and `.gitignore`
- [ ] docs

## Test Cases

- contract CI job green; web Vitest and build green
- local generator spike compiles and the four fields are optionals
- AASA served on the dev stack with and without `APPLE_APP_ID` set
- SPA history fallback unchanged for unrelated paths

## Docs To Update

- `docs/architecture.md` (contract section: generator-friendly nullable style; hosting: `.well-known` routes)
- `docs/deployment.md` (`APPLE_APP_ID`, how to change the Team ID or bundle id)

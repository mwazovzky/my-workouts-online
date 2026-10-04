# Epic: iOS App

**Status: in progress (stories 1–5 shipped, story 6 started 2026-10-04).**

Ship a native iOS client for the existing `/api/v1` contract so a user can register, enroll, log workouts and manage their profile from an iPhone, with the web SPA untouched and the API as the single source of truth.

## Why This Epic Exists

The API-First Decoupling epic finished the groundwork: a token issuer that already distinguishes `client: native` and returns the refresh token in the body, an OpenAPI 3.1 contract enforced in CI, header-driven locale negotiation, and same-origin hosting any client can use. Nothing on the backend is web-specific any more. The iOS app is the first proof that the decoupling bought something: a second client built against the contract without changing the API, except for a handful of generator-compatibility edits to the spec and one nginx location for Universal Links.

Decisions the earlier epic already recorded and this epic inherits: `apps/ios` as the location, both tokens in the Keychain, String Catalogs for UI strings, the same hostname as the web app.

## Decisions

Cost of a third codebase for a solo maintainer is the main constraint. Every choice below favours fewer tools and parity with the web client over novelty.

| # | Decision | Choice |
| --- | --- | --- |
| 1 | Technology | Swift 6, SwiftUI, iOS 17 minimum (`@Observable`, `NavigationStack`, String Catalogs; covers the vast majority of active devices). MVVM: `@Observable` view models, one repository protocol per feature, an `AppSession` object in the environment. |
| 2 | Scope of v1 | User features only: auth, dashboard, programs and enrollment, template detail, workout logging, history, profile. Admin catalog CRUD stays web-only; admins see the same four tabs. |
| 3 | Offline | Online-only. The workout being edited keeps a local draft that survives an app kill; nothing else is cached. No offline-first sync. |
| 4 | Project definition | XcodeGen `apps/ios/project.yml`; the `.xcodeproj` is generated and git-ignored. YAML is reviewable and editable by tooling and avoids `pbxproj` merge conflicts. Fallback if the extra tool becomes a burden: a committed `.xcodeproj` using Xcode 16 synchronized folders. Tuist rejected as too heavy for one app. |
| 5 | Code layout | One app target `MyWorkouts` (`apps/ios/App/`) plus local Swift packages in `apps/ios/Packages/`: `WorkoutsAPI` (generated client, transport, middlewares, error mapping) and `WorkoutsCore` (domain models, session, Keychain, locale, repositories, view models, draft store). Packages are Foundation-only so `swift test` runs them without a simulator. Views live in the app target. |
| 6 | API client | Apple `swift-openapi-generator` as a SwiftPM build plugin in `WorkoutsAPI`, reading `contracts/openapi.yaml` through a symlink, with `openapi-generator-config.yaml` filtering to the non-admin tags. Generated code is never committed, so the contract stays the single source of truth and compiling the package in CI is the drift check. A mapping layer in `WorkoutsCore` references every field the app uses, so a removed field is a compile error, not a silent `nil`. Server URL is injected at runtime (the contract's `servers` entry is relative). Bearer, `Accept-Language`, `User-Agent` and refresh-and-retry are `ClientMiddleware`s. If the generator rejects a construct, the spec is fixed (it must stay valid for the web and Schemathesis), never hand-written around. |
| 7 | Tokens and session | Both tokens in the Keychain (`kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly`), access token mirrored in memory. An `AuthSession` actor does single-flight refresh. Only a 401 with code `token_expired` triggers a refresh, exactly as `apps/web/src/lib/api.js` does. Any 401 from the refresh call itself (`refresh_token_invalid`, `_expired`, `_reused`) signs the user out and clears the Keychain; a network error during refresh keeps the tokens and is retryable. Launch bootstrap = refresh if the access token is expired, then `GET /me`. `device_name` = device model plus iOS version (`UIDevice.current.name` is generic since iOS 16). |
| 8 | Deep links | Universal Links on the production domain for `/verify-email/...` and `/reset-password/...`. The AASA file is served by nginx from the web image, templated with `APPLE_APP_ID` (`TEAMID.bundleid`) by `docker/web/start.sh`. A custom URL scheme exists only as a simulator fallback. Web links keep working for users without the app; the backend keeps sending `FRONTEND_URL` links unchanged. |
| 9 | Workout draft | Unsaved editor state serialized as `Codable` JSON to Application Support `Drafts/workout-{id}.json`, saved debounced and on backgrounding, restored on reopen only when the server `updated_at` still matches the draft's base; deleted on save, complete, delete, repeat and sign-out. |
| 10 | i18n | `Localizable.xcstrings` with `en` and `ru`, keyed by the English sentence exactly like the web catalogs, seeded once by a script from `apps/web/src/i18n/locales/{en,ru}.json`. The UI language follows the iOS per-app language; `Accept-Language` is derived from the same resolution mapped onto `/meta` `available_locales`, fallback `en`. Profile shows a Language row that opens iOS Settings, and the app syncs `PATCH /profile/locale` when the resolved language differs from `user.locale`. Theme keeps an in-app picker like the web. |
| 11 | Environment config | `API_BASE_URL` per xcconfig: Debug `http://localhost:8080` (the dev Docker stack), Release the production domain; an optional git-ignored `Local.xcconfig` for a LAN IP when testing on a device. `NSAllowsLocalNetworking` in `Info.plist` so plain-HTTP `localhost` passes App Transport Security. |
| 12 | Formatting and lint | `swift-format` from the Xcode toolchain (`swift format lint --strict`), configured in `apps/ios/.swift-format`. No Homebrew linters; the only host tools are Xcode and XcodeGen. |
| 13 | Testing | Swift Testing for unit tests in the packages (session, middleware against a stub transport, view models against mock repositories, draft store, deep-link parser); XCTest only for a small XCUITest smoke suite. Mocks sit at the repository protocol level, not at the generated client. |
| 14 | CI | `.github/workflows/ios.yml`: an Ubuntu `changes` job using `dorny/paths-filter`, then `build-and-test` on a macOS runner gated with `if:` on `apps/ios/**`, `contracts/openapi.yaml` and the workflow file. A job skipped by `if:` still satisfies branch protection, so unrelated PRs merge without spending macOS minutes while the check stays required. |
| 15 | Release | Tags `ios-v*`, never `v*` (which triggers `deploy.yml`). Manual Xcode archive to TestFlight for the first releases; Xcode Cloud (free tier, cloud signing) is the upgrade path if cadence demands it. fastlane rejected (Ruby toolchain on a host that runs only Xcode). Sign in with Apple is not required while the only login is email and password; App Store guideline 4.8 applies once a third-party login exists. |

## Target Architecture

```
contracts/openapi.yaml ──symlink──▶ apps/ios/Packages/WorkoutsAPI/Sources/WorkoutsAPI/openapi.yaml
                                     swift-openapi-generator build plugin (types + client, non-admin tags)

apps/ios/
  project.yml                  XcodeGen: app target, packages, xcconfigs, test targets
  App/                         MyWorkoutsApp, RootView, MainTabView, feature views,
                               Localizable.xcstrings, Assets, Info.plist, entitlements
  Config/                      Base / Debug / Release / Versions / Local (git-ignored) .xcconfig
  Packages/
    WorkoutsAPI/               generated Client + Types, AuthMiddleware, LocaleMiddleware,
                               UserAgentMiddleware, APIError mapping, StubTransport (tests)
    WorkoutsCore/              domain models + mappers, AuthSession, KeychainTokenStore,
                               AppSession, LocaleResolver, DeepLinkParser, WorkoutDraftStore,
                               repositories and view models per feature
  UITests/                     XCUITest smoke flows
  scripts/                     seed-string-catalog.swift

Request flow: View -> ViewModel -> Repository -> generated Client
  -> LocaleMiddleware (Accept-Language) -> AuthMiddleware (bearer; on 401 token_expired:
     single-flight refresh, retry once) -> URLSessionTransport -> https://{DOMAIN}/api/v1

Session state (AppSession): .bootstrapping -> .signedOut -> .unverified(user) -> .ready(user)
RootView:                    splash           auth flow     verify-email gate  MainTabView
                                                                               (Dashboard, Programs, Workouts, Profile)
```

## Scope

- native SwiftUI app in `apps/ios` covering every user-facing (non-admin) web page
- generated Swift client from the shared contract, plus the minimal contract edits the generator needs
- Universal Links for the two emailed links, including the nginx location and the AASA file
- CI job, TestFlight pipeline, App Store submission
- `docs/` updated as each story ships

## Non-Goals

- admin catalog management on iOS
- offline-first or background sync; only the unsaved editor draft survives an app kill
- Sign in with Apple or Google (the contract's `socialLogin` endpoint stays `x-status: target` for a later epic)
- push notifications, reminders, widgets, HealthKit, watchOS, iPad-specific layouts (the app runs on iPad in compatibility mode)
- device session list or remote revocation (no endpoint exists)
- Android, or any product feature the web app does not have

## Story Order

| # | Story | Primary feature | State |
| --- | --- | --- | --- |
| 1 | [Backend and Repo Prerequisites](stories/01-backend-and-repo-prerequisites.md) | Platform | shipped (#31) |
| 2 | [Xcode Project Scaffold, Tooling and CI](stories/02-xcode-project-scaffold-and-ci.md) | Platform | shipped (#32) |
| 3 | [Generated API Client](stories/03-generated-api-client.md) | Platform | shipped (#33) |
| 4 | [Session, Keychain and Auth Middleware](stories/04-session-keychain-and-auth-middleware.md) | Auth & Profile | shipped (#34) |
| 5 | [Auth Screens](stories/05-auth-screens.md) | Auth & Profile | shipped (#35) |
| 6 | [Universal Links: Verify Email and Reset Password](stories/06-universal-links-verify-and-reset.md) | Auth & Profile | in progress |
| 7 | [App Shell and Dashboard](stories/07-app-shell-and-dashboard.md) | Workout Logging | planned |
| 8 | [Programs and Workout Templates](stories/08-programs-and-workout-templates.md) | Programs | planned |
| 9 | [Workout Editor: Sets and Save](stories/09-workout-editor-sets-and-save.md) | Workout Logging | planned |
| 10 | [Workout Editor: Activities, Complete and Local Draft](stories/10-workout-editor-activities-complete-and-draft.md) | Workout Logging | planned |
| 11 | [Workout History and Detail](stories/11-workout-history-and-detail.md) | Workout Logging | planned |
| 12 | [Profile](stories/12-profile.md) | Auth & Profile | planned |
| 13 | [Polish, Accessibility and Current-State Docs](stories/13-polish-accessibility-and-docs.md) | Platform | planned |
| 14 | [TestFlight and App Store Release](stories/14-testflight-and-app-store-release.md) | Platform | planned |

States: `planned`, `in progress`, `shipped`, `deferred`. Update the row when a story's PR opens and when it merges; archive or delete the story file after it ships, per `planning/README.md`.

## Sequencing Rationale

- Story 1 is backend and contract only. It ships first so the web keeps working, the generator has a compatible spec before any Swift exists, and the AASA location is live long before a device needs it.
- Story 2 lands the project together with the CI gate, so every later iOS PR is checked from day one.
- Stories 3 and 4 build the two cross-cutting layers (client, session) with unit tests against a stub transport, before any screen.
- Auth screens and deep links (5 and 6) come before content so every later story can be exercised end to end on a real device with a real account.
- Shell, dashboard, programs (7 and 8) are the read-mostly screens and establish the shared loading, empty and error states.
- The editor (9 and 10) is the core value and gets two stories: the set rules first, then activities, completion and the draft.
- History and profile (11 and 12) complete parity with the web.
- Polish and docs (13) and release (14) are intentionally last.

## Success Metrics

- every API call in the app goes through the generated client; zero hand-written request structs
- a contract change that removes or renames a field the app uses fails the `ios` CI job at compile time
- the web SPA and the Schemathesis contract job are unaffected by story 1
- a TestFlight build on a real device completes register, verify via Universal Link, enroll, log and complete a workout against production

## Risks

- `swift-openapi-generator` silently drops properties declared as `anyOf`/`oneOf` of a `$ref` and `null`, and does not handle `const`. The contract has four such properties and two `const`s. Mitigated in story 1 by reshaping them, and by the mapping layer that references every field.
- Universal Links can only be verified against the production domain with the real Team ID. Mitigated by shipping the nginx location in story 1, by using Apple's AASA validator, and by a dedicated device test in story 6.
- Refresh-token rotation on a flaky network: a lost refresh response leaves the client holding a consumed token, and the next refresh revokes the family. Mitigated by persisting the new pair before any other request proceeds and by never auto-retrying the refresh call on a network error. Accepted as a rare forced sign-in.
- macOS runner minutes cost ten times Linux minutes. Mitigated by the gated job; UI tests run only on tags and manual dispatch.
- A third codebase for a solo maintainer. Mitigated by excluding admin and offline, by the shared contract, and by mirroring the web client's rules instead of redesigning them.

## Done Criteria

This epic is done when:

- all fourteen stories are shipped or explicitly deferred
- a TestFlight or App Store build logs a full workout against the production API
- the `ios` job is green and marked required on `main`
- `docs/` describes the iOS client as current state (architecture, routes, deployment, features)
- `apps/ios` has no dependency on the backend beyond `/api/v1` and the two deep-link paths

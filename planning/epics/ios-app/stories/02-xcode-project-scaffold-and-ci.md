# User Story: Xcode Project Scaffold, Tooling and CI

Create the iOS project in `apps/ios` with configuration, local packages, formatting, a seeded String Catalog, and a CI job that runs only when iOS-relevant files change.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: None
- Creates New Feature: Yes (Platform: iOS client)

## Problem Statement

There is no iOS project, no build configuration, no formatting rule, no UI string catalog and no CI gate for Swift code.

## User-Visible Outcome

None. An empty app launches in the simulator and shows a placeholder screen in English or Russian.

## Scope

- `apps/ios/project.yml` (XcodeGen): app target `MyWorkouts`, iOS 17.0 deployment target, SwiftUI lifecycle, Swift 6 language mode, test targets `MyWorkoutsTests` and `MyWorkoutsUITests`, local package dependencies `Packages/WorkoutsAPI` and `Packages/WorkoutsCore`, xcconfig per configuration
- `apps/ios/App/`: `MyWorkoutsApp.swift`, `RootView.swift` placeholder, `Info.plist` with `APIBaseURL = $(API_BASE_URL)` and `NSAppTransportSecurity.NSAllowsLocalNetworking = true`, `Assets.xcassets`, `Localizable.xcstrings`
- `apps/ios/Config/{Base,Debug,Release,Versions}.xcconfig` with `API_BASE_URL` (Debug `http:/$()/localhost:8080`, Release the production domain), `PRODUCT_BUNDLE_IDENTIFIER`, `MARKETING_VERSION`, `CURRENT_PROJECT_VERSION`; `#include? "Local.xcconfig"` for a git-ignored LAN override
- `AppConfig` reading `APIBaseURL` from the bundle
- `Packages/WorkoutsCore` and `Packages/WorkoutsAPI` with empty library targets and one trivial Swift Testing test each
- `apps/ios/scripts/seed-string-catalog.swift`: reads `apps/web/src/i18n/locales/{en,ru}.json`, writes or merges `Localizable.xcstrings` (source `en`), converts Laravel `:name` placeholders to positional `%1$@` and prints the converted keys for review; idempotent; never overwrites an edited translation
- `apps/ios/.swift-format`; `apps/ios/README.md` with prerequisites (Xcode, `brew install xcodegen`), `xcodegen generate`, running against the dev stack, formatting and test commands
- `.github/workflows/ios.yml`: Ubuntu `changes` job with `dorny/paths-filter` on `apps/ios/**`, `contracts/openapi.yaml`, `.github/workflows/ios.yml`; `build-and-test` on the current macOS runner image gated by `if: needs.changes.outputs.ios == 'true'`; steps: pin Xcode via `DEVELOPER_DIR`, cache SwiftPM, `brew install xcodegen`, `xcodegen generate`, `swift format lint --strict --recursive apps/ios`, `swift test` in each package, `xcodebuild test` on an iPhone simulator destination skipping UI tests, upload the `.xcresult` on failure
- branch protection: mark `ios / build-and-test` required (a skipped job counts as passing)
- `CLAUDE.md` gains the `apps/ios` component, the commands, and the rule "after modifying any `.swift` file, run `swift format` and the affected tests"

## Non-Goals

- networking, auth, or any real screen
- UI tests beyond the generated template
- SwiftLint or other Homebrew linters

## Acceptance Criteria

- fresh clone, `xcodegen generate`, build and run shows the placeholder in the simulator; switching the simulator language to Russian shows the Russian placeholder
- the Debug build reads `http://localhost:8080` from `AppConfig`; Release reads the production URL
- `swift format lint --strict` passes; both packages' tests pass with `swift test`
- a PR touching only `apps/web` shows `ios / build-and-test` skipped and mergeable; a PR touching `apps/ios` runs the macOS job and passes
- the seeding script run twice produces identical output

## Initial Tasks

- [ ] `project.yml`, xcconfigs, `Info.plist`, `AppConfig`
- [ ] package skeletons with one test each
- [ ] seeding script and initial `Localizable.xcstrings`
- [ ] `.swift-format`, `apps/ios/README.md`
- [ ] `ios.yml` and branch protection
- [ ] `CLAUDE.md`, root `README.md`, `docs/README.md`, `docs/testing.md`

## Test Cases

- trivial test per package passes locally and in CI
- CI on a docs-only PR shows the skip; CI on an `apps/ios` PR runs the macOS job
- seeding: placeholder conversion sample (`:completed/:total sets`), idempotence, preserved manual edit

## Docs To Update

- `README.md` (repository layout, local iOS run)
- `CLAUDE.md` (component, commands, post-edit rule)
- `docs/README.md` (index entry)
- `docs/testing.md` (iOS section)

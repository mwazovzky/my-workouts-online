# MyWorkouts iOS

Native SwiftUI client for the API in `services/api-php`, built against `contracts/openapi.yaml`. Planning and story status: `planning/epics/ios-app/`.

## Prerequisites

- Xcode 26 or newer with an iOS simulator runtime (`xcodebuild -downloadPlatform iOS`)
- XcodeGen: `brew install xcodegen`
- The dev stack running (`docker compose up -d` at the repo root) for anything that talks to the API

## Run

```bash
cd apps/ios
xcodegen generate          # writes MyWorkouts.xcodeproj (git-ignored); rerun after editing project.yml
open MyWorkouts.xcodeproj  # pick an iPhone simulator and run
```

The Debug configuration points at `http://localhost:8080` (the dev stack); Release points at production. Both come from `Config/*.xcconfig`, read through `Info.plist` by `AppConfig`. For a physical device on your LAN, copy `Config/Local.xcconfig.example` to `Config/Local.xcconfig` and set `API_BASE_URL` and `DEVELOPMENT_TEAM`.

## Layout

```
project.yml            XcodeGen project: one app target, two test targets, two local packages
App/                   SwiftUI app target: entry point, views, Info.plist, assets, Localizable.xcstrings
Config/                Base / Debug / Release / Versions .xcconfig (+ git-ignored Local.xcconfig)
Packages/WorkoutsCore  domain, session, locale, repositories, view models (Foundation-only)
Packages/WorkoutsAPI   generated OpenAPI client and middlewares
Tests/, UITests/       app-target unit tests (Swift Testing) and XCUITest smoke tests
scripts/               seed-string-catalog.swift
```

## Commands

```bash
swift format lint --strict --recursive apps/ios          # lint (from the repo root)
swift format --in-place --recursive apps/ios             # format
(cd apps/ios/Packages/WorkoutsCore && swift test)        # package tests, no simulator needed
(cd apps/ios/Packages/WorkoutsAPI && swift test)
xcodebuild test -project apps/ios/MyWorkouts.xcodeproj -scheme MyWorkouts \
  -destination 'platform=iOS Simulator,name=iPhone 18 Pro' -skip-testing:MyWorkoutsUITests \
  -skipPackagePluginValidation            # the OpenAPI generator is a build plugin
swift apps/ios/scripts/seed-string-catalog.swift         # merge web UI strings into the String Catalog
```

## API client

`Packages/WorkoutsAPI` generates its client at build time with Apple's `swift-openapi-generator` from `contracts/openapi.yaml`, which is symlinked into the package as `Sources/WorkoutsAPI/openapi.yaml`. Nothing generated is committed. `openapi-generator-config.yaml` limits generation to the non-admin tags. The first time Xcode builds the project it asks to trust the plugin; choose "Trust & Enable". On the command line pass `-skipPackagePluginValidation`.

`Packages/WorkoutsCore/Sources/WorkoutsCore/Mapping` converts the generated `Components.Schemas.*` types into domain models and references every field the app uses, so a contract change that removes a field fails the build. `APIError` (in `WorkoutsAPI`) is the one error type views handle: `.validation` with per-field messages, `.api` with the server's `code`, `.network`, `.decoding`.

Tests in `WorkoutsCore` replay JSON captured from the seeded dev API (`Tests/WorkoutsCoreTests/Fixtures`) through the real client. Refresh a fixture by running the same request against `http://localhost:8080` as the demo admin and saving the body.

## Localization

`App/Localizable.xcstrings` holds `en` and `ru`, keyed by the English sentence exactly like `apps/web/src/i18n/locales`. The seeding script adds missing keys and never overwrites an edited translation. The UI language follows the iOS per-app language setting; `LocaleResolver` derives the `Accept-Language` value from the same preference.

## Conventions

- Swift 6 language mode with strict concurrency.
- Views live in the app target; everything testable without UIKit lives in `Packages/`.
- After editing any `.swift` file run `swift format --in-place` and the affected tests.
- Releases are tagged `ios-vX.Y.Z`. Never use `v*`, which deploys the web and API.

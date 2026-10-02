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
  -destination 'platform=iOS Simulator,name=iPhone 18 Pro' -skip-testing:MyWorkoutsUITests
swift apps/ios/scripts/seed-string-catalog.swift         # merge web UI strings into the String Catalog
```

## Localization

`App/Localizable.xcstrings` holds `en` and `ru`, keyed by the English sentence exactly like `apps/web/src/i18n/locales`. The seeding script adds missing keys and never overwrites an edited translation. The UI language follows the iOS per-app language setting; `LocaleResolver` derives the `Accept-Language` value from the same preference.

## Conventions

- Swift 6 language mode with strict concurrency.
- Views live in the app target; everything testable without UIKit lives in `Packages/`.
- After editing any `.swift` file run `swift format --in-place` and the affected tests.
- Releases are tagged `ios-vX.Y.Z`. Never use `v*`, which deploys the web and API.

# User Story: TestFlight and App Store Release

Produce signed builds for testers and publish the app.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Auth & Profile (review demo account, account deletion)
- Creates New Feature: No

## Problem Statement

There is no Apple Developer setup, no signing, no reproducible way to produce a build for testers, and no store listing.

## User-Visible Outcome

Testers receive builds through TestFlight; the app is available on the App Store.

## Scope

- Apple Developer account, Team ID, bundle identifier; record both in the epic README and in story 1's `APPLE_APP_ID` for the AASA file
- App Store Connect app record; signing with Xcode automatic signing
- `PrivacyInfo.xcprivacy` (UserDefaults reason code; no tracking), App Privacy answers (account and workout data linked to the user, no tracking), export compliance (HTTPS only, exempt)
- `Config/Versions.xcconfig`: `MARKETING_VERSION` bumped per release, `CURRENT_PROJECT_VERSION` incremented per upload
- release runbook: bump version, tag `ios-vX.Y.Z` (never `v*`, which deploys the web and API), archive in Xcode, upload, TestFlight internal group, then App Review; Xcode Cloud as the documented upgrade path
- `ios.yml`: run the XCUITest suite on `ios-v*` tags and `workflow_dispatch`
- store listing in `en` and `ru`: description, screenshots, age rating, support URL and privacy policy URL (static pages served by the web SPA if missing), review notes with a demo account registered and verified on production
- confirm in-app account deletion for guideline 5.1.1(v); Sign in with Apple not required (no third-party login)

## Non-Goals

- external TestFlight groups, paid features, marketing site

## Acceptance Criteria

- pushing `ios-v0.1.0` does not trigger `deploy.yml`
- a TestFlight build points at the production base URL, installs on a device, and completes a full workout flow including a Universal Link
- the privacy manifest and App Privacy answers pass App Review; the build is approved and released

## Initial Tasks

- [ ] developer account, bundle id, App Store Connect record
- [ ] privacy manifest and answers
- [ ] versions and tag convention; `ios.yml` tag trigger for UI tests
- [ ] policy and support pages
- [ ] first TestFlight upload, demo account, submission

## Test Cases

- dry-run `ios-v*` tag on a branch shows no deploy run in Actions
- fresh install from TestFlight on a real device completes register, verify via link, enroll, log, complete
- store links resolve; demo account signs in

## Docs To Update

- `docs/deployment.md` (iOS release runbook, store checklist)
- `README.md` (release tags)
- `CLAUDE.md` (deployment line)
- `docs/product.md` (platforms, one line)

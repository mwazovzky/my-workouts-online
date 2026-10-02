# User Story: Universal Links: Verify Email and Reset Password

Open the emailed verification and reset links in the app when it is installed, and finish the flow there.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: Platform (entitlement, device testing)
- Creates New Feature: No

## Problem Statement

Verification and reset emails link to the web SPA. On a phone with the app installed the user should finish in the app. The verify endpoint requires being signed in as the linked user, which needs explicit handling when the app is signed out or signed in as someone else.

## User-Visible Outcome

Tapping an emailed link opens the app and completes verification or password reset, with a clear message when the user must sign in first or is signed in as a different account.

## Scope

- Associated Domains entitlement `applinks:{production domain}` in Release; Debug may add `?mode=developer`
- `DeepLinkParser.parse(URL) -> DeepLink?` for `/verify-email/{id}/{hash}?expires&signature` and `/reset-password/{token}?email`; foreign paths return `nil`
- `.onOpenURL` on the root stores the link in `AppSession.pendingDeepLink`
- verify handling: `.ready`/`.unverified` with matching user id → `POST /auth/email/verify` → `refreshUser()` → main view; `.signedOut` → login screen with "Sign in to finish verifying your email", then verify after sign-in; different user → alert explaining the link belongs to another account, with a Sign out action
- reset handling: `ResetPasswordView(token:email:)` presented as a sheet in any state; on success the app signs out locally (the server revoked every family) and shows "Password updated, sign in again"
- custom URL scheme `myworkouts://` mapped to the same parser for `xcrun simctl openurl` testing
- `docs/deployment.md`: post-deploy check that the AASA URL returns 200 `application/json` and the Team ID matches

## Non-Goals

- deep links to programs or workouts
- in-app browser fallback

## Acceptance Criteria

- `xcrun simctl openurl` with both custom-scheme URLs lands on the right screen in the simulator
- on a real device with a signed build, a verification email link opens the app and verifies the account; a reset link opens the reset sheet
- signed out → login → auto-verify → main view; mismatched user → alert; the same links still open the web SPA on a desktop browser
- Apple's AASA validator reports the production file as valid

## Initial Tasks

- [ ] entitlement and URL scheme in `project.yml`
- [ ] parser and pending-link state machine in `WorkoutsCore`
- [ ] reset-password screen and verify coordinator
- [ ] device test with a real email; runbook check

## Test Cases

- parser unit tests: both shapes, missing query parameters, foreign paths, custom scheme
- coordinator tests for the three verify states and for reset success/failure
- device test documented in the PR

## Docs To Update

- `docs/features/auth-and-profile.md` (links open the app when installed)
- `docs/deployment.md` (AASA verification step)

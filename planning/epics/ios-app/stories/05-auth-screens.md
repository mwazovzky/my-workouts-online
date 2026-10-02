# User Story: Auth Screens

Let a user register, sign in, request a password reset and get through the verify-email gate.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

Nobody can sign in. The session layer exists but has no screens.

## User-Visible Outcome

Users register, log in, see the verify-email gate with a resend button, and request a password reset. Errors from the API appear under the right field in the user's language.

## Scope

- `WelcomeView`, `LoginView`, `RegisterView`, `ForgotPasswordView`, `VerifyEmailGateView`
- view models over `AuthRepository`: `login` and `register` send `client: native` and `device_name`; `forgotPassword` always shows the neutral 202 message; `resendVerification` handles 202 (sent) and 204 (already verified, refresh `/me`)
- form error binding: `APIError.validation` to per-field messages, including the login lockout returned as a 422 on `email`; 429 shows the throttle message
- `RootView` gating: `.unverified` shows the gate with "I have verified" (re-fetches `/me`), resend, and sign out
- optional "remember email" in `UserDefaults` (never the password)

## Non-Goals

- Universal Links and the reset-password screen (story 6)
- social login, biometrics

## Acceptance Criteria

- register leads to the gate; after verifying on the web and tapping "I have verified", the app continues to the main view
- login with a wrong password shows the API message under the password field; the sixth attempt shows the lockout message under email
- resend shows a throttle message on 429 and a confirmation on 202
- a verified user bypasses the gate on the next cold launch

## Initial Tasks

- [ ] views
- [ ] view models and form error binding helper
- [ ] gate behaviour and sign-out
- [ ] tests and one XCUITest for the login happy path

## Test Cases

- view model tests for each form with stubbed 200, 422 (field errors, lockout), 429
- gate: 204 on resend refreshes `/me` and moves to `.ready`
- UI smoke: login happy path lands on the main view

## Docs To Update

- `docs/features/auth-and-profile.md` (iOS flows)
- `docs/pages-and-routes.md` (iOS screens section)

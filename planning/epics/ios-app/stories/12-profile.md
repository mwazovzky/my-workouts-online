# User Story: Profile

Manage the account from the Profile tab: details, password, language, theme, deletion and sign-out.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

The Profile tab is a placeholder with only sign-out.

## User-Visible Outcome

Users edit name and email (email change returns them to the verify gate), change their password, see and change the app language, pick a theme, delete the account with password confirmation, sign out, and see the app version.

## Scope

- profile form over `updateProfile`; when `email_verified` is false show the resend button from story 5
- password form over `updatePassword` (`current_password`, `password`, `password_confirmation`)
- Language row: shows the effective language and opens the app's page in iOS Settings (`UIApplication.openSettingsURLString`); on launch and on becoming active, if the resolved language differs from `user.locale`, call `updateLocale` and refresh the user, so the web picks up the same preference
- theme picker over `updateTheme` with options from `/meta` `theme_preferences`; applies immediately
- delete account over `deleteProfile` with the password in the body; success clears the session and returns to Welcome
- sign-out row; version and build from the bundle
- all updates adopt the returned `User`

## Non-Goals

- in-app language picker that overrides the OS language
- avatar, notifications, sessions list

## Acceptance Criteria

- changing the email moves the user to the verify gate; name change updates the header immediately
- wrong current password shows the API message under the field
- changing the per-app language in iOS Settings and returning updates `user.locale` on the server once
- theme changes apply at once and persist across launches
- delete with a wrong password shows the error; with the right password signs out and returns to Welcome

## Initial Tasks

- [ ] forms and view models
- [ ] language row and locale sync
- [ ] theme picker and persistence
- [ ] delete account and sign-out
- [ ] tests

## Test Cases

- profile and password view models with 200 and 422
- locale sync decision table (same, different, unsupported device language)
- theme mapping and persistence
- delete flow success and failure

## Docs To Update

- `docs/features/auth-and-profile.md` (profile on iOS, locale sync rule)
- `docs/pages-and-routes.md`

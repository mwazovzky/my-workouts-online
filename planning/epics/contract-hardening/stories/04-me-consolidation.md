# User Story: `/me` Consolidation

Give the current user one resource: `GET /me`, `PATCH /me` as a merge patch, `PUT /me/password`, `DELETE /me`; retire `/profile*` and server-side password confirmation.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: none
- Creates New Feature: No

## Problem Statement

The current user is read at `/me` but written at `/profile`, `/profile/locale`, `/profile/theme` and `/profile/password`. `PATCH /profile` requires both `name` and `email`, which is PUT behaviour under a PATCH verb, and two more PATCH endpoints exist only because that one cannot update a single field. `register`, `resetPassword` and `updatePassword` require `password_confirmation`, a Laravel form convention that a JSON API does not need — both clients already compare the two fields before sending.

## User-Visible Outcome

None. The profile page saves name, email, language and theme exactly as before.

## Scope

- Contract: `PATCH /me` with body `MePatch` (`name`, `email`, `locale`, `theme_preference`, all optional, `minProperties: 1`, `additionalProperties: false`) returning `UserResponse`; `PUT /me/password` with `{current_password, password}` → 204; `DELETE /me` with `{password}` → 204; remove `/profile`, `/profile/password`, `/profile/locale`, `/profile/theme`; remove `password_confirmation` from `RegisterRequest`, the reset body and the password body. OperationIds: `updateMe`, `updatePassword`, `deleteMe`.
- PHP: `routes/api.php` routes; `ProfileController` becomes `MeController` with `update`, `updatePassword`, `destroy`; `ProfileUpdateRequest`, `ProfileLocaleRequest`, `ProfileThemeRequest` merge into `MeUpdateRequest` with `sometimes` rules; `email` change still nulls `email_verified_at`; `RegisterRequest` and `ResetPasswordRequest` drop `confirmed`.
- Web: `apps/web/src/pages/Profile/Partials/*` (four forms) call `PATCH /me` with only their fields; `UpdatePasswordForm.vue` and `Register.vue`/`ResetPassword.vue` keep the confirmation input client-side and send `password` only; `apps/web/src/stores/auth.js` user refresh unchanged.
- iOS: `MeRepository.swift` and `ProfileViewModel.swift` use `updateMe`; `AuthRepository.swift` register/reset bodies drop the confirmation field; `LocaleResolver` sync calls `PATCH /me {locale}`.
- `scripts/contract-test.sh` excludes `updateMe` and `deleteMe` (replacing `updateProfile` and `deleteProfile`); `.github/workflows/contract.yml` unchanged.
- `npm run api:types` regenerated.

## Non-Goals

- changing password rules
- a separate email-change confirmation flow
- removing `reauth`

## Acceptance Criteria

- Redocly lint green; Spectral green with the PATCH-semantics rule enabled
- `scripts/contract-test.sh` green on the dev stack
- PHP suite green; web `npm test` and `npm run build` green; iOS `swift test` in `WorkoutsCore` and `xcodebuild test` green
- `npm run api:types` regenerated
- `PATCH /me {"locale":"ru"}` changes only the locale; `PATCH /me {}` → 422; `PATCH /me {"email": …}` unverifies the account
- `PUT /me/password` with a wrong `current_password` → 422 `mismatch` on `/current_password`
- `POST /auth/register` with `password_confirmation` present → 422 (unknown key)
- no `/api/v1/profile*` route answers anything but 404

## Initial Tasks

- [ ] contract paths, `MePatch`, body changes, operationIds
- [ ] routes, `MeController`, `MeUpdateRequest`, request cleanups
- [ ] web profile partials, register and reset pages
- [ ] iOS repositories and view models
- [ ] contract-test exclusions
- [ ] regenerate web types; docs

## Test Cases

- `tests/Feature/Api/ProfileApiTest.php` → `MeApiTest.php` (merge): each field alone, empty body, unknown key, email change unverifies, password change revokes other families, delete revokes all
- `tests/Feature/Api/Auth/RegisterTest.php`, `PasswordResetTest.php`: no confirmation field
- `apps/web/src/stores/auth.test.js`; new tests for the profile partials' request bodies
- iOS `ProfileViewModelTests.swift`, `AuthViewModelTests.swift`, `LocaleResolverTests.swift`

## Docs To Update

- `docs/features/auth-and-profile.md` (profile endpoints, merge-patch semantics, no confirmation field)
- `docs/pages-and-routes.md` (profile page)
- `docs/architecture.md` (routing conventions: one resource for the current user)

# User Story: Session, Keychain and Auth Middleware

Store and rotate tokens in the Keychain, refresh once on `token_expired`, and drive the root view from the session state.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: Platform
- Creates New Feature: No

## Problem Statement

Native clients receive the refresh token in the response body and must store, rotate and revoke it themselves. A burst of expired requests must trigger exactly one refresh, and a revoked family must sign the user out cleanly.

## User-Visible Outcome

A signed-in user stays signed in across launches for up to 30 days of inactivity, and is returned to the login screen with a short explanation when the session is revoked.

## Scope

- `KeychainTokenStore`: one generic-password item holding `TokenPair { accessToken, refreshToken, accessExpiresAt }`, `kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly`, service = bundle id; a protocol so tests use an in-memory store
- `actor AuthSession`: `currentAccessToken()`, `refresh()` with an in-flight task so concurrent callers coalesce, `signIn(_ response:)`, `signOut(revoke:)`; persists the new pair before returning
- `AuthMiddleware: ClientMiddleware`: adds the bearer; on 401 buffers the body, decodes `code`, and when it is `token_expired` and the operation is not `refresh`, `login` or `register`, awaits `refresh()` and replays the request once; any other 401 passes through
- `AppSession` (`@Observable`, main actor): `state` (`.bootstrapping`, `.signedOut(reason?)`, `.unverified(User)`, `.ready(User)`), `user`, `bootstrap()`, `apply(authResponse:)`, `refreshUser()`, `signOut()`
- bootstrap: no pair → `.signedOut`; pair with a fresh access token → `GET /me`; expired → refresh then `/me`; any auth failure → `.signedOut`
- `MeRepository` (`getMe`), `AuthRepository` skeleton for `login`, `register`, `refresh`, `logout` sending `client: native` and `device_name` = `"\(model) · iOS \(systemVersion)"`
- `RootView` switching between a placeholder auth flow, a placeholder verify gate and a placeholder main view
- logout: `POST /auth/logout` best effort, then clear the Keychain and drafts

## Non-Goals

- real auth screens (story 5)
- step-up `reauth` (no v1 screen needs it; profile deletion takes the password in the body)

## Acceptance Criteria

- bootstrap with a fresh pair reaches `.ready` with no refresh call; with an expired access token it makes exactly one refresh call
- three concurrent 401 `token_expired` responses produce exactly one `POST /auth/refresh` and all three original requests are replayed and succeed
- a 401 with `refresh_token_reused` from the refresh call clears the Keychain and lands on `.signedOut(.sessionExpired)`
- a network error during refresh keeps the stored pair and surfaces as a retryable error
- logout calls `/auth/logout` with the bearer and clears everything even when that call fails

## Initial Tasks

- [ ] Keychain wrapper and in-memory test double
- [ ] `AuthSession` actor
- [ ] `AuthMiddleware`
- [ ] `AppSession` and `RootView` switch
- [ ] repositories and tests with `StubTransport`

## Test Cases

- all acceptance criteria above
- retry is skipped for `refresh`, `login`, `register` operations
- a 401 with code `unauthenticated` is passed through without a refresh
- Keychain accessibility attribute asserted on the real store (simulator)

## Docs To Update

- `docs/features/auth-and-profile.md` (native client session flow)
- `docs/architecture.md` (session section)

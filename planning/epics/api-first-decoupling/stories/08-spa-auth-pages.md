# User Story: SPA Auth Pages

Rebuild login, registration, forgot password, reset password, verify email, and re-authentication on the API auth endpoints.

## Feature Mapping

- Primary Feature: Auth & Profile
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

The six auth pages use Inertia `useForm` against Breeze web routes and read a session flash `status`.

## User-Visible Outcome

Users complete every auth flow inside the SPA. Email links open SPA routes that finish the flow.

## Scope

- pages rewritten over `useApiForm` and the auth store
- routes: `/login`, `/register`, `/forgot-password`, `/reset-password/:token`, `/verify-email`, `/verify-email/:id/:hash`, `/confirm-password` (step-up via `/auth/reauth`, returns to `?redirect`)
- `verified` guard redirects to `/verify-email`; resend button calls `/auth/email/resend`
- step-up: `/confirm-password` page calls `/auth/reauth` and records `recentlyAuthenticatedAt` in the auth store; password change and account deletion already re-check the password server-side, so no page requires step-up yet
- "Remember me" dropped: the 30-day refresh cookie already keeps web sessions alive
- success and error feedback via toasts and coded messages

## Non-Goals

- social login buttons (contract only)

## Acceptance Criteria

- each flow succeeds end to end against the API
- validation errors display per field from the coded shape
- verification link from the email verifies the user and lands on the dashboard

## Initial Tasks

- page rewrites
- route entries and guards
- reauth dialog component
- tests

## Test Cases

- login success stores token and navigates to intended route
- register success shows verify prompt
- reset with invalid token shows coded error
- reauth dialog appears when required and unlocks the action

## Docs To Update

- docs/features/auth-and-profile.md

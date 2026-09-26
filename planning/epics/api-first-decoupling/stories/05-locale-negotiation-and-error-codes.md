# User Story: Locale Negotiation and Error Codes

Make the API locale-negotiated by header and give every error a stable machine-readable code.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Auth & Profile, Admin Content Management
- Creates New Feature: No

## Problem Statement

`SetLocale` falls back to a session value that only exists in the Inertia app, and validation errors are bare strings, so clients cannot render their own wording or branch on failure reasons.

## User-Visible Outcome

Database content (program, exercise, equipment, category names) arrives in the language the client is rendering. Error messages in every client match the client's language.

## Scope

- API group gets `NegotiateApiLocale`: `Accept-Language` is authoritative, negotiated against `available_locales`; the web group keeps `SetLocale` (user, session, header) until story 10; the Inertia app sends `Accept-Language` from the page locale
- responses set `Content-Language` and `Vary: Accept-Language`
- error envelope `{ message, code }` for all non-2xx responses via the exception handler
- validation errors `{ message, code: "validation_failed", errors: { field: [{ code, message }] } }` where `code` is the Laravel rule name
- 404 on `api/*` keeps `Record not found.` with code `not_found`
- `PATCH /locale` web route marked deprecated (removed in story 10)

## Non-Goals

- translating UI strings on the server

## Acceptance Criteria

- a request with `Accept-Language: ru` returns Russian content and `Content-Language: ru`
- an unsupported language falls back to `en`
- every validation failure returns the coded shape
- 401, 403, 404, 409, 422, 429 all use the envelope

## Initial Tasks

- middleware change and response header middleware
- exception handler rendering for `api/*`
- update `useApiForm` in the current frontend to read `errors[field][0].message`
- tests

## Test Cases

- header negotiation for `ru`, `en`, unsupported, and missing
- content translation fallback to English per field
- validation error shape for a nested field (`activities.0.sets.1.effort_value`)
- envelope on each status code

## Docs To Update

- docs/architecture.md (i18n section)
- docs/pages-and-routes.md

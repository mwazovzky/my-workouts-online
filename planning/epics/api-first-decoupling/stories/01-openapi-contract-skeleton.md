# User Story: OpenAPI Contract Skeleton

Describe the `/api/v1` surface in `contracts/openapi.yaml` so every client and backend has one target to implement against.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: Auth & Profile, Programs, Workout Logging, Admin Content Management
- Creates New Feature: Yes (Platform: API contract)

## Problem Statement

The API exists only as Laravel routes and resources. Nothing machine-readable describes it, so a second client or backend has no contract to build from and no way to prove conformance.

## User-Visible Outcome

None directly. Developers get a single spec from which TypeScript and Swift clients are generated and against which responses are validated.

## Scope

- document every existing `/api/v1` endpoint, request body, and response shape
- document the target auth, `me`, `meta`, and JWKS endpoints
- define the shared error envelope and validation error shape
- define pagination, locale negotiation headers, and security schemes

## Non-Goals

- generating clients (done in the SPA story)
- contract test tooling (story 11)

## Acceptance Criteria

- `contracts/openapi.yaml` validates as OpenAPI 3.1
- every route in `routes/api.php` has a path item
- every resource in `app/Http/Resources` has a schema
- error envelope and validation error shape are defined once and referenced everywhere

## Initial Tasks

- derive schemas from `app/Http/Resources/*` and `app/Http/Requests/*`
- write path items for existing routes
- write path items for target auth endpoints
- add the epic brief and stories to `planning/`

## Test Cases

- spec passes an OpenAPI 3.1 validator

## Docs To Update

- none until the contract is enforced (story 11 adds `docs/architecture.md` section)

# User Story: Contract Tests in CI

Validate every API response against `contracts/openapi.yaml` in CI so the spec is enforced and becomes the acceptance gate for a replacement backend.

## Feature Mapping

- Primary Feature: Platform
- Secondary Features: None
- Creates New Feature: No

## Problem Statement

A spec that is not enforced drifts. The Go backend needs an executable definition of "compatible".

## User-Visible Outcome

None.

## Scope

- contract job that boots the API with seeded data and runs schema validation of responses (Schemathesis or Spectator)
- spec lint in CI (Spectral or equivalent)
- documented command to run the suite against any base URL

## Non-Goals

- performance or load testing

## Acceptance Criteria

- CI fails when a response deviates from the spec
- the suite runs against an arbitrary base URL via one env var

## Initial Tasks

- tooling choice and config
- seed fixture for contract run
- workflow job

## Test Cases

- deliberately break a resource field locally and observe the failure

## Docs To Update

- docs/testing.md
- docs/architecture.md (contract section)

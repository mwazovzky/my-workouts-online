#!/usr/bin/env bash
# Validates a running API against contracts/openapi.yaml with Schemathesis.
#
#   scripts/contract-test.sh [base-url] [email] [password]
#
# The account must be a verified admin so every documented operation is
# reachable. Point the base URL at any implementation of the contract (the
# PHP API today, another backend tomorrow). Set SCHEMATHESIS to a local
# binary to skip Docker. Fuzzing writes data: run it against a disposable
# database.
#
# CONTRACT_INCLUDE limits the run to a comma-separated list of operationIds,
# for a backend that implements only part of the contract (the Go API while
# its epic is in progress). When every listed operation is public (security:
# [] in the contract), the login step is skipped, so no account is needed.

set -euo pipefail

BASE_URL="${1:-${CONTRACT_BASE_URL:-http://localhost:8080}}"
EMAIL="${2:-${CONTRACT_EMAIL:-test@example.com}}"
PASSWORD="${3:-${CONTRACT_PASSWORD:-password}}"
MAX_EXAMPLES="${CONTRACT_MAX_EXAMPLES:-5}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
INCLUDE="${CONTRACT_INCLUDE:-}"

include_args=()
needs_login=true
if [[ -n "${INCLUDE}" ]]; then
  public_ops=" $(awk '/operationId:/ { id = $2 } /security: \[\]/ { print id }' "${ROOT}/contracts/openapi.yaml" | tr '\n' ' ') "
  needs_login=false
  IFS=',' read -r -a included <<< "${INCLUDE}"
  for op in "${included[@]}"; do
    include_args+=(--include-operation-id "${op}")
    [[ "${public_ops}" == *" ${op} "* ]] || needs_login=true
  done
fi

auth_args=()
if [[ "${needs_login}" == true ]]; then
  token=$(curl --fail --silent --show-error -X POST "${BASE_URL}/api/v1/auth/login" \
    -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\",\"client\":\"native\"}" \
    | python3 -c 'import sys, json; print(json.load(sys.stdin)["access_token"])')

  if [[ -z "${token}" ]]; then
    echo "Could not obtain an access token from ${BASE_URL}." >&2
    exit 1
  fi
  auth_args=(--header "Authorization: Bearer ${token}")
fi

# Operations excluded from fuzzing because they would break the run itself:
# logout revokes the token in use, deleteMe/updateMe removes or renames
# the account, and the social login endpoint is not implemented yet.
common_args=(
  # The ${a[@]+...} form keeps bash 3.2 (macOS) happy with an empty array under set -u.
  ${auth_args[@]+"${auth_args[@]}"}
  ${include_args[@]+"${include_args[@]}"}
  --header "Accept: application/json"
  --header "X-Requested-With: XMLHttpRequest"
  --checks all
  --exclude-checks ignored_auth,unsupported_method,allow_header_conformance,positive_data_acceptance
  --max-examples "${MAX_EXAMPLES}"
  --workers 1
  --phases examples,coverage,fuzzing
  --exclude-operation-id socialLogin
  --exclude-operation-id logout
  --exclude-operation-id updateMe
  --exclude-operation-id deleteMe
  --report junit
)

if [[ -n "${SCHEMATHESIS:-}" ]]; then
  exec "${SCHEMATHESIS}" --config-file "${ROOT}/contracts/schemathesis.toml" \
    run "${ROOT}/contracts/openapi.yaml" --url "${BASE_URL}" \
    "${common_args[@]}" --report-dir "${ROOT}/contracts/.report"
fi

docker_args=(--rm -v "${ROOT}/contracts:/contracts")
docker_url="${BASE_URL}"
if [[ "$(uname -s)" == "Darwin" ]]; then
  docker_url="${docker_url//localhost/host.docker.internal}"
  docker_url="${docker_url//127.0.0.1/host.docker.internal}"
else
  docker_args+=(--network host)
fi

exec docker run "${docker_args[@]}" schemathesis/schemathesis:stable \
  --config-file /contracts/schemathesis.toml \
  run /contracts/openapi.yaml --url "${docker_url}" "${common_args[@]}" --report-dir /contracts/.report

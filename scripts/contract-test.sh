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

set -euo pipefail

BASE_URL="${1:-${CONTRACT_BASE_URL:-http://localhost:8080}}"
EMAIL="${2:-${CONTRACT_EMAIL:-test@example.com}}"
PASSWORD="${3:-${CONTRACT_PASSWORD:-password}}"
MAX_EXAMPLES="${CONTRACT_MAX_EXAMPLES:-5}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

token=$(curl --fail --silent --show-error -X POST "${BASE_URL}/api/v1/auth/login" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\",\"client\":\"native\"}" \
  | python3 -c 'import sys, json; print(json.load(sys.stdin)["access_token"])')

if [[ -z "${token}" ]]; then
  echo "Could not obtain an access token from ${BASE_URL}." >&2
  exit 1
fi

# Operations excluded from fuzzing because they would break the run itself:
# logout revokes the token in use, profile delete/update removes or renames
# the account, and the social login endpoint is not implemented yet.
common_args=(
  --header "Authorization: Bearer ${token}"
  --header "Accept: application/json"
  --header "X-Requested-With: XMLHttpRequest"
  --checks all
  --exclude-checks ignored_auth,unsupported_method,allow_header_conformance,positive_data_acceptance
  --max-examples "${MAX_EXAMPLES}"
  --workers 1
  --phases examples,coverage,fuzzing
  --exclude-operation-id socialLogin
  --exclude-operation-id logout
  --exclude-operation-id updateProfile
  --exclude-operation-id deleteProfile
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

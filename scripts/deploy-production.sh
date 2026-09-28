#!/usr/bin/env bash

set -euo pipefail

compose_file="docker-compose.prod.yml"

if [[ -z "${IMAGE_TAG:-}" ]]; then
  echo "IMAGE_TAG is required."
  exit 1
fi

if [[ ! -f ".env" ]]; then
  echo "Missing .env in $(pwd)."
  exit 1
fi

if [[ ! -f "${compose_file}" ]]; then
  echo "Missing ${compose_file} in $(pwd)."
  exit 1
fi

if [[ -n "${DEPLOY_REGISTRY_USERNAME:-}" && -n "${DEPLOY_REGISTRY_TOKEN:-}" ]]; then
  echo "Logging in to Docker Hub on the droplet..."
  printf '%s' "${DEPLOY_REGISTRY_TOKEN}" | docker login --username "${DEPLOY_REGISTRY_USERNAME}" --password-stdin
fi

if [[ -f scripts/renew-certs.sh ]]; then
  install -m 0755 scripts/renew-certs.sh /home/deploy/renew-certs.sh
fi

tmp_env_file=$(mktemp)
updated_image_tag=false

while IFS= read -r line; do
  if [[ "${line}" == IMAGE_TAG=* ]]; then
    printf 'IMAGE_TAG=%s\n' "${IMAGE_TAG}" >> "${tmp_env_file}"
    updated_image_tag=true
  elif [[ "${line}" == APP_VERSION=* ]]; then
    printf 'APP_VERSION=%s\n' "${IMAGE_TAG}" >> "${tmp_env_file}"
  else
    printf '%s\n' "${line}" >> "${tmp_env_file}"
  fi
done < .env

if [[ "${updated_image_tag}" != true ]]; then
  rm -f "${tmp_env_file}"
  echo "IMAGE_TAG entry was not found in .env."
  exit 1
fi

mv "${tmp_env_file}" .env

echo "Deploying IMAGE_TAG=${IMAGE_TAG}..."
docker compose -f "${compose_file}" up -d mysql
docker compose -f "${compose_file}" pull api web worker alloy
docker compose -f "${compose_file}" up -d --no-deps --force-recreate api
docker compose -f "${compose_file}" up -d --no-deps --force-recreate alloy
docker compose -f "${compose_file}" stop worker

echo "Waiting for MySQL to accept connections..."
for attempt in {1..20}; do
  if docker compose -f "${compose_file}" exec -T mysql sh -lc 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null 2>&1; then
    break
  fi

  if [[ "${attempt}" -eq 20 ]]; then
    echo "MySQL did not become ready in time."
    exit 1
  fi

  sleep 3
done

docker compose -f "${compose_file}" exec -T api php artisan migrate --force
docker compose -f "${compose_file}" exec -T api php artisan optimize
docker compose -f "${compose_file}" up -d --no-deps --force-recreate web
docker compose -f "${compose_file}" up -d --no-deps --force-recreate worker

# Runs after every service is back up: a failed sync fails the deploy, but
# writes nothing and leaves the stack running on the new release.
echo "Syncing the catalog..."
docker compose -f "${compose_file}" exec -T api php artisan catalog:sync

if [[ -n "${DEPLOY_HEALTHCHECK_URL:-}" ]]; then
  echo "Checking ${DEPLOY_HEALTHCHECK_URL}..."
  curl --fail --show-error --silent --retry 10 --retry-delay 3 --retry-connrefused "${DEPLOY_HEALTHCHECK_URL}" >/dev/null
fi

if [[ -n "${DEPLOY_READYCHECK_URL:-}" ]]; then
  echo "Checking ${DEPLOY_READYCHECK_URL}..."
  curl --fail --show-error --silent --retry 10 --retry-delay 3 --retry-connrefused "${DEPLOY_READYCHECK_URL}" >/dev/null
fi

docker image prune -f

docker compose -f "${compose_file}" ps
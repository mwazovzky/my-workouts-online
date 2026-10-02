#!/bin/sh
set -eu

: "${PHP_FPM_UPSTREAM:=api:9000}"
: "${DOMAIN_NAME:=_}"
: "${APPLE_APP_ID:=}"

NGINX_TEMPLATE="/etc/nginx/templates/default.conf.template"
if [ -f "/etc/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem" ] && [ -f /etc/nginx/templates/default-ssl.conf.template ]; then
	NGINX_TEMPLATE="/etc/nginx/templates/default-ssl.conf.template"
fi

envsubst '${PHP_FPM_UPSTREAM} ${DOMAIN_NAME}' < "$NGINX_TEMPLATE" > /etc/nginx/conf.d/default.conf

# Universal Links: render the AASA with the Team ID + bundle id, or an empty
# document when no iOS app is published yet.
if [ -n "$APPLE_APP_ID" ]; then
	envsubst '${APPLE_APP_ID}' < /etc/nginx/templates/apple-app-site-association.template > /etc/nginx/apple-app-site-association
else
	cp /etc/nginx/templates/apple-app-site-association.empty /etc/nginx/apple-app-site-association
fi

exec nginx -g "daemon off;"

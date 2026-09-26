#!/bin/sh
set -eu

: "${PHP_FPM_UPSTREAM:=api:9000}"
: "${DOMAIN_NAME:=_}"

NGINX_TEMPLATE="/etc/nginx/templates/default.conf.template"
if [ -f "/etc/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem" ] && [ -f /etc/nginx/templates/default-ssl.conf.template ]; then
	NGINX_TEMPLATE="/etc/nginx/templates/default-ssl.conf.template"
fi

envsubst '${PHP_FPM_UPSTREAM} ${DOMAIN_NAME}' < "$NGINX_TEMPLATE" > /etc/nginx/conf.d/default.conf

exec nginx -g "daemon off;"

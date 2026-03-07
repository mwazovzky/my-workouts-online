#!/bin/sh
set -euo pipefail

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

: "${APP_ROOT:=/var/www/html/public}"
: "${PHP_FPM_UPSTREAM:=127.0.0.1:9000}"
: "${DOMAIN_NAME:=_}"

NGINX_TEMPLATE="/etc/nginx/templates/default.conf.template"
if [ -f "/etc/letsencrypt/live/${DOMAIN_NAME}/fullchain.pem" ] && [ -f /etc/nginx/templates/default-ssl.conf.template ]; then
	NGINX_TEMPLATE="/etc/nginx/templates/default-ssl.conf.template"
fi

if [ -f "$NGINX_TEMPLATE" ]; then
	mkdir -p /etc/nginx/http.d
	envsubst '${PHP_FPM_UPSTREAM} ${APP_ROOT} ${DOMAIN_NAME}' < "$NGINX_TEMPLATE" > /etc/nginx/http.d/default.conf
fi

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf

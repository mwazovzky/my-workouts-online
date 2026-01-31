#!/bin/sh
set -euo pipefail

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

: "${APP_ROOT:=/var/www/html/public}"
: "${PHP_FPM_UPSTREAM:=127.0.0.1:9000}"

if [ -f /etc/nginx/templates/default.conf.template ]; then
	mkdir -p /etc/nginx/http.d
	envsubst '${PHP_FPM_UPSTREAM} ${APP_ROOT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf
fi

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf

# syntax=docker/dockerfile:1.7
ARG PHP_VERSION=8.4

# --- Stage 1: PHP Dependencies (Composer) ---
FROM composer:2.8 AS composer-deps
WORKDIR /app
COPY composer*.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# --- Stage 2: Frontend Build (Node.js) ---
# Use a glibc-based image so Rollup's native optional deps resolve reliably.
FROM node:20-bookworm-slim AS frontend-builder
WORKDIR /app
COPY package*.json ./
## NOTE: npm has a long-standing bug with optional dependencies in some Docker/buildx setups
## (commonly surfacing as missing @rollup/rollup-* packages). This install approach trades a bit of
## determinism for reliability of production builds.
RUN rm -f package-lock.json && npm install --no-audit --no-fund
COPY . .
COPY --from=composer-deps /app/vendor /app/vendor
RUN npm run build

# --- Stage 3: Base PHP image (shared) ---
FROM php:${PHP_VERSION}-fpm-alpine AS php-base
WORKDIR /var/www/html

RUN apk add --no-cache \
    curl \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    gettext \
    nginx \
    supervisor

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip opcache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf

# --- Stage 4: Dev PHP-FPM (bind-mounted code) ---
FROM php-base AS app-dev
CMD ["php-fpm"]

# --- Stage 5: Production web image (nginx + php-fpm) ---
FROM php-base AS web
ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}
ENV APP_ROOT=/var/www/html/public
ENV PHP_FPM_UPSTREAM=127.0.0.1:9000

COPY . /var/www/html
COPY --from=composer-deps /app/vendor /var/www/html/vendor
COPY --from=frontend-builder /app/public/build /var/www/html/public/build

# Don't bake local/dev cached manifests into the production image
RUN rm -f /var/www/html/bootstrap/cache/*.php || true

# Ensure Laravel/Vite never thinks a dev server is running in production
RUN rm -f /var/www/html/public/hot || true

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/nginx/default-ssl.conf.template /etc/nginx/templates/default-ssl.conf.template
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/web/start.sh /usr/local/bin/start-web

RUN chmod +x /usr/local/bin/start-web \
    && mkdir -p /run/php /var/log/supervisor

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80 443
HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD pgrep php-fpm >/dev/null 2>&1 || exit 1

CMD ["/usr/local/bin/start-web"]

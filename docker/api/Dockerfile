# syntax=docker/dockerfile:1.7
# API image: php-fpm running the Laravel app in services/api-php. nginx lives
# in the web image and reaches this one over FastCGI.
ARG PHP_VERSION=8.4

# --- Stage 1: PHP dependencies (Composer) ---
FROM composer:2.8 AS composer-deps
WORKDIR /app
COPY services/api-php/composer*.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# --- Stage 2: base PHP image (shared) ---
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
    icu-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip opcache

COPY docker/api/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/api/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf

# --- Stage 3: dev php-fpm (code bind-mounted) ---
FROM php-base AS app-dev
CMD ["php-fpm"]

# --- Stage 4: production API image ---
FROM php-base AS api
ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}

COPY services/api-php /var/www/html
COPY --from=composer-deps /app/vendor /var/www/html/vendor

# Runtime directories are excluded from the build context; create them and
# never bake local caches or dev-server markers into the production image.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && rm -f bootstrap/cache/*.php public/hot \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD pgrep php-fpm >/dev/null 2>&1 || exit 1

CMD ["php-fpm"]

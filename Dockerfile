# syntax=docker/dockerfile:1.7

# ─────────────────────────────────────────────────────────────────────────────
# HotFii production image.
#
# Stages:
#   base    PHP 8.4 FPM with the extensions and CLI tools the app needs.
#   vendor  Composer dependencies (no dev packages by default).
#   assets  Vite production build of the AdminLTE/Bootstrap frontend.
#   app     Final PHP-FPM runtime, also used for queue, scheduler and Reverb.
#   web     Nginx with an immutable copy of public/ from the app stage.
# ─────────────────────────────────────────────────────────────────────────────

FROM php:8.4-fpm-bookworm AS base

# freeradius-utils supplies radclient, which GenericRadiusAdapter::disconnect()
# executes for RADIUS CoA. postgresql-client supplies pg_isready and psql for
# the entrypoint readiness check and the RADIUS grant script.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        freeradius-utils \
        postgresql-client \
        gosu \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        zlib1g-dev \
        unzip \
        git \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        sockets \
        zip \
    ; \
    rm -rf /var/lib/apt/lists/*

COPY infrastructure/docker/php.ini /usr/local/etc/php/conf.d/zz-hotfii.ini
COPY infrastructure/docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-hotfii.conf

WORKDIR /var/www/html


FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Leave empty to include dev dependencies, which is what running the test
# suite inside a container requires.
ARG COMPOSER_FLAGS="--no-dev"
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./

# --no-scripts because package:discover needs the application source, which is
# not copied yet. The final stage runs it once everything is in place.
RUN composer install ${COMPOSER_FLAGS} \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist


FROM node:22-bookworm-slim AS assets

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci

# resources/js/echo.js only wires up Laravel Echo when VITE_REVERB_APP_KEY is
# present, and Vite resolves import.meta.env at build time. There is no .env in
# the build context, so these have to arrive as build args or realtime silently
# falls back to polling. Compose passes them from the REVERB_* values in .env.
ARG VITE_REVERB_APP_KEY=""
ARG VITE_REVERB_HOST=""
ARG VITE_REVERB_PORT="443"
ARG VITE_REVERB_SCHEME="https"
ARG VITE_APP_NAME="HotFii"
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME} \
    VITE_APP_NAME=${VITE_APP_NAME}

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build


FROM base AS app

ARG COMPOSER_FLAGS="--no-dev"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /build/public/build ./public/build

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN set -eux; \
    composer dump-autoload ${COMPOSER_FLAGS} --optimize --classmap-authoritative; \
    php artisan package:discover --ansi; \
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

COPY infrastructure/docker/entrypoint.sh /usr/local/bin/hotfii-entrypoint
COPY infrastructure/postgres/grant-radius.sql /usr/local/share/hotfii/grant-radius.sql
RUN chmod +x /usr/local/bin/hotfii-entrypoint

ENTRYPOINT ["hotfii-entrypoint"]
CMD ["app"]


FROM nginx:1.27-alpine AS web

# public/ is baked in rather than shared through a volume, so a rebuilt app can
# never be served alongside a previous release's asset manifest.
COPY --from=app /var/www/html/public /var/www/html/public
COPY infrastructure/nginx/templates/hotfii.conf.template /etc/nginx/templates/hotfii.conf.template

# The stock default.conf would sort before hotfii.conf and claim the default
# server for port 80, answering IP requests with the Nginx welcome page.
RUN rm -f /etc/nginx/conf.d/default.conf

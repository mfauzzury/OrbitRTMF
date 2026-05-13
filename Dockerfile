# syntax=docker/dockerfile:1
# OrbitRTMF / CORRAD: Vue SPA + Laravel API in one image (Coolify-friendly).
# Domains and secrets: set in Coolify Environment Variables / Build Arguments — not here.

# -----------------------------------------------------------------------------
# Stage 1: build Vue client (Vite)
# -----------------------------------------------------------------------------
FROM node:22-bookworm AS frontend

WORKDIR /app/client

COPY client/package.json client/package-lock.json ./
RUN npm ci

COPY client/ ./

# Do not ENV-map Coolify's VITE_API_BASE_URL — it often points at the wrong host and breaks same-origin SPA+API.
# Same-origin UI+API: omit SPA_API_BASE_URL (recommended for this Dockerfile).
# Split subdomain: in Coolify set build-arg SPA_API_BASE_URL=https://your-api.example.com (not VITE_API_BASE_URL).
ARG SPA_API_BASE_URL=
# Set VITE only for this RUN so stray build-time ENV from the host cannot overwrite the SPA base URL.
RUN export VITE_API_BASE_URL="${SPA_API_BASE_URL}" && npm run build

# -----------------------------------------------------------------------------
# Stage 2: PHP-FPM + Nginx (PHP 8.4+ required by Symfony 8 / current composer.lock)
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive \
    APP_ENV=production

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        git \
        zip \
        unzip \
        libpq-dev \
        default-libmysqlclient-dev \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pdo_mysql opcache pcntl bcmath zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --from=frontend /app/client/dist /var/www/html/spa

COPY docker/nginx/app.conf /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default

COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up >/dev/null || exit 1

ENTRYPOINT ["/entrypoint.sh"]

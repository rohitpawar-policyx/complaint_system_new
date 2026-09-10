# syntax=docker/dockerfile:1

# ---- Stage 1: build frontend assets ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# ---- Stage 2: install PHP dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---- Stage 3: runtime image ----
FROM php:8.2-cli-bookworm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev unzip git libonig-dev libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["entrypoint.sh"]

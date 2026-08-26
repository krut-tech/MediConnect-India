# ---- Stage 1: Build frontend assets (Vite/Tailwind) ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# ---- Stage 2: PHP application ----
FROM php:8.2-apache

# System dependencies + PHP extensions needed for Laravel + Postgres (Supabase)
RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application code
COPY . .

# Remove any stale bootstrap cache that may have been committed from a
# local dev machine — these confuse package discovery inside the container.
RUN rm -f bootstrap/cache/*.php

# Bring in built frontend assets from stage 1
COPY --from=assets /app/public/build ./public/build

RUN test -f public/build/manifest.json \
    && test -d public/build/assets
# Laravel's artisan bootstrapping (used by package:discover during
# composer install) needs a .env file to exist, even a dummy one — real
# values come from Render's environment variables at runtime and take
# precedence over anything in this file.
RUN cp .env.example .env

# Install PHP dependencies (production only). Scripts run normally now
# that .env exists, so package:discover succeeds and registers the
# framework's core providers (filesystem, view, etc.) correctly.
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Generate a temporary build-time APP_KEY so artisan doesn't complain;
# Render's real APP_KEY env var overrides this at runtime.
RUN php artisan key:generate --ansi --force

# Laravel needs these directories writable
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Serve the Laravel public/ directory, not the repo root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/sites-available/default-ssl.conf 2>/dev/null || true

# Serve Laravel public directory and Vite/Tailwind build assets
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/sites-available/default-ssl.conf 2>/dev/null || true

RUN printf '%s\n' \
    '<Directory /var/www/html/public>' \
    '    AllowOverride All' \
    '    Require all granted' \
    '    Options FollowSymLinks -MultiViews' \
    '</Directory>' \
    '' \
    '<Directory /var/www/html/public/build>' \
    '    AllowOverride None' \
    '    Require all granted' \
    '    Options FollowSymLinks' \
    '</Directory>' \
    '' \
    'Alias /build/ /var/www/html/public/build/' \
    > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Render injects $PORT at runtime; container must listen on it
EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]

# ============================================================
# Stage 1: Build frontend assets (Vite + Tailwind)
# ============================================================
FROM node:20-alpine AS assets

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./

RUN npm run build

# Verify Vite production assets were generated
RUN test -f public/build/.vite/manifest.json \
    && test -d public/build/assets \
    && test -n "$(find public/build/assets -type f | head -n 1)"


# ============================================================
# Stage 2: Laravel + Apache
# ============================================================
FROM php:8.2-apache

# ------------------------------------------------------------
# System dependencies + PHP extensions
# ------------------------------------------------------------
RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*


# ------------------------------------------------------------
# Composer
# ------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


# ------------------------------------------------------------
# Laravel application
# ------------------------------------------------------------
WORKDIR /var/www/html

COPY . .

# Remove stale Laravel caches
RUN rm -f bootstrap/cache/*.php


# ------------------------------------------------------------
# Copy production Vite/Tailwind assets
# ------------------------------------------------------------
COPY --from=assets /app/public/build ./public/build

RUN test -f public/build/manifest.json \
    && test -d public/build/assets


# ------------------------------------------------------------
# Temporary build-time environment
# Real Render environment variables override these at runtime
# ------------------------------------------------------------
RUN cp .env.example .env


# ------------------------------------------------------------
# Install Laravel dependencies
# ------------------------------------------------------------
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist


# ------------------------------------------------------------
# Temporary build-time APP_KEY
# ------------------------------------------------------------
RUN php artisan key:generate --ansi --force


# ------------------------------------------------------------
# Laravel writable directories
# ------------------------------------------------------------
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
    && chmod -R 775 \
        storage \
        bootstrap/cache


# ============================================================
# Apache configuration
# ============================================================

# IMPORTANT:
# Do NOT use APACHE_DOCUMENT_ROOT + sed here.
# We explicitly configure the correct Laravel public directory.

RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /var/www/html/public' \
    '' \
    '    <Directory /var/www/html/public>' \
    '        AllowOverride All' \
    '        Require all granted' \
    '        Options FollowSymLinks' \
    '    </Directory>' \
    '' \
    '    <Directory /var/www/html/public/build>' \
    '        AllowOverride None' \
    '        Require all granted' \
    '        Options FollowSymLinks' \
    '    </Directory>' \
    '' \
    '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
    '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf


# Laravel Apache configuration
RUN printf '%s\n' \
    '<Directory /var/www/html/public>' \
    '    AllowOverride All' \
    '    Require all granted' \
    '    Options FollowSymLinks' \
    '</Directory>' \
    '' \
    '<Directory /var/www/html/public/build>' \
    '    AllowOverride None' \
    '    Require all granted' \
    '    Options FollowSymLinks' \
    '</Directory>' \
    > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel


# ------------------------------------------------------------
# Render entrypoint
# ------------------------------------------------------------
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh


# Render injects $PORT at runtime
EXPOSE 10000


ENTRYPOINT ["/entrypoint.sh"]

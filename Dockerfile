# ============================================================
# Stage 1 — Build Vite + Tailwind assets
# ============================================================
FROM node:20-alpine AS assets

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./

RUN npm run build

# Laravel Vite v5 stores the manifest here
RUN test -f public/build/.vite/manifest.json \
    && test -d public/build/assets \
    && test -n "$(find public/build/assets -type f | head -n 1)"


# ============================================================
# Stage 2 — Laravel + Apache
# ============================================================
FROM php:8.2-apache

# ------------------------------------------------------------
# PHP / system dependencies
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

RUN rm -f bootstrap/cache/*.php


# ------------------------------------------------------------
# Copy Vite/Tailwind production build
# ------------------------------------------------------------
COPY --from=assets /app/public/build ./public/build

# Verify the files copied correctly
RUN test -f public/build/.vite/manifest.json \
    && test -d public/build/assets \
    && test -n "$(find public/build/assets -type f | head -n 1)"


# ------------------------------------------------------------
# Temporary build environment
# ------------------------------------------------------------
RUN cp .env.example .env


# ------------------------------------------------------------
# PHP dependencies
# ------------------------------------------------------------
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist


# ------------------------------------------------------------
# Temporary APP_KEY for build
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
# Apache
# ============================================================

# Remove default SSL site; Render uses HTTP internally
RUN a2dissite default-ssl 2>/dev/null || true


# Laravel public directory
RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    ServerName localhost' \
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


# Additional Laravel directory permissions
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


# Validate Apache configuration during image build
RUN apache2ctl configtest


# ============================================================
# Render entrypoint
# ============================================================
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh


# Render supplies the actual PORT at runtime
EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]

#!/bin/sh
set -e

# Render sets $PORT dynamically; default to 10000 for local docker run
PORT="${PORT:-10000}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Warn if APP_KEY missing (set it in Render's environment variables)
if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY is not set. Run 'php artisan key:generate --show' locally and add it to Render env vars."
fi

# Cache config/routes/views for production performance (safe to run every boot)
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

exec apache2-foreground

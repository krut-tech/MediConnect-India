#!/bin/sh
set -e

# Render sets $PORT dynamically; default to 10000 for local docker run
PORT="${PORT:-10000}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# APP_KEY must be a FIXED value set in Render's environment variables.
# If it is not set, the container silently falls back to the throwaway
# key baked into the image at build time (see Dockerfile: `php artisan
# key:generate --force` runs against the temporary build .env on EVERY
# image build). That baked key is different on every deploy, and Laravel's
# EncryptCookies middleware encrypts the session-id cookie with APP_KEY —
# so every already-issued session/CSRF cookie silently breaks the moment
# a new deploy goes live. This is the confirmed root cause of production
# 419 PAGE EXPIRED errors on /login (see Render logs 2026-08-27/28: 419s
# cluster on deploys/instances, not on any CSRF/session code path).
#
# Failing fast here in production turns a silent, intermittent CSRF
# failure into an immediate, obvious boot error — instead of shipping a
# key rotation no one asked for.
if [ -z "$APP_KEY" ]; then
    if [ "$APP_ENV" = "production" ]; then
        echo "FATAL: APP_KEY is not set in the Render environment. Refusing to start in production."
        echo "Run 'php artisan key:generate --show' locally, add the result as APP_KEY in Render's" >&2
        echo "environment variables (Dashboard > Service > Environment), then redeploy." >&2
        exit 1
    fi
    echo "WARNING: APP_KEY is not set. Run 'php artisan key:generate --show' locally and add it to Render env vars."
fi

# Cache config/routes/views for production performance (safe to run every boot)
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

exec apache2-foreground

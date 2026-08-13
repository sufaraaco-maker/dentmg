#!/bin/sh
set -e

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
  php artisan key:generate --force
fi

# Phase 5B (Notification System): the queue worker and scheduler containers introduced in that
# phase share this image and therefore this entrypoint. Exactly ONE container must run migrations
# — three racing `migrate --force` calls at boot can interleave badly — so those two set
# RUN_MIGRATIONS=false and let the `app` container remain the single migrator. They still get the
# vendor/.env/APP_KEY bootstrapping above, which they genuinely need.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force

  # Clinic logo / user avatar uploads (2026-08-13) serve from the `public` disk, which needs this
  # symlink to be web-reachable at all. Kept inside the same single-owner branch as `migrate`
  # above rather than its own top-level check — three containers racing `storage:link` (it errors
  # if the target already exists) is the identical hazard `RUN_MIGRATIONS` was introduced to avoid.
  if [ ! -L public/storage ]; then
    php artisan storage:link
  fi
else
  # Wait for the app container to finish migrating before starting work, rather than crash-looping
  # against a half-migrated schema.
  until php artisan migrate:status >/dev/null 2>&1; do
    echo "Waiting for database migrations to complete..."
    sleep 2
  done
fi

# The backend/ bind mount is created with root ownership on first run, but php-fpm
# workers run as www-data (see php-fpm.d/www.conf) and need write access to log,
# cache compiled views, and write framework cache/session files.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"

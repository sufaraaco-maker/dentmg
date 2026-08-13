#!/bin/sh
set -e

if [ ! -f .env ]; then
  echo "FATAL: backend/.env is missing. Production must not fall back to .env.example" \
       "(that file targets local development — APP_DEBUG=true, verbose logging, no secure-cookie" \
       "flags). Provision a real .env from backend/.env.production.example with production" \
       "secrets before starting this container." >&2
  exit 1
fi

if [ "$(php -r "echo trim(getenv('APP_ENV') ?: '');" 2>/dev/null)" = "local" ]; then
  echo "FATAL: APP_ENV=local in a container started from Dockerfile.prod — refusing to start." \
       "This guards against accidentally pointing the production stack at a dev .env file." >&2
  exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
  echo "FATAL: APP_KEY is not set. Run 'php artisan key:generate' once, store the resulting" \
       "value in your secrets manager, and never regenerate it on a running deployment" \
       "(it would invalidate every session and any encrypted column)." >&2
  exit 1
fi

chown -R www-data:www-data storage bootstrap/cache

# Clinic logo / user avatar uploads (2026-08-13) serve from the `public` disk, which needs this
# symlink to be web-reachable at all. Guarded the same way `docker/php/entrypoint.sh` guards
# `migrate`/`storage:link` — `app`/`queue`/`scheduler` share this image, and `storage:link` errors
# if the target already exists, so only the single container that isn't `RUN_MIGRATIONS: 'false'`
# (i.e. `app`) creates it.
if [ "${RUN_MIGRATIONS:-true}" = "true" ] && [ ! -L public/storage ]; then
  php artisan storage:link
fi

# Config/route/view caches are safe to rebuild on every boot (idempotent, no state loss) and
# meaningfully cheaper than resolving config/routes on every request. Database migrations are
# deliberately NOT run here — see docs/deployment.md "Migrations" for the explicit, operator-run
# deploy step this is intentionally decoupled from (auto-migrating on container boot means a
# crash-looping container can hammer `migrate` repeatedly, and makes rollback harder to reason
# about).
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"

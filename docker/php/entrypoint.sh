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

php artisan migrate --force

# The backend/ bind mount is created with root ownership on first run, but php-fpm
# workers run as www-data (see php-fpm.d/www.conf) and need write access to log,
# cache compiled views, and write framework cache/session files.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"

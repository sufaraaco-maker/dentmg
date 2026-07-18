#!/bin/bash
# Restore a DentalSuite backup produced by backup.sh. Deliberately requires two explicit
# arguments (no "latest" auto-detection) so a restore is never triggered by a typo — see
# docs/deployment.md "Restore procedure" for the full runbook, including the required
# maintenance-mode step before running this against a live production database.
#
# Usage: restore.sh <database.dump> <storage.tar.gz>
set -euo pipefail

if [ "$#" -ne 2 ]; then
  echo "Usage: $0 <path-to-database.dump> <path-to-storage.tar.gz>" >&2
  exit 1
fi

DB_DUMP="$1"
STORAGE_ARCHIVE="$2"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# Override with e.g. COMPOSE_FILE=docker-compose.yml to rehearse against a non-production stack
# (see docs/deployment.md "Testing restore") — production use should never set this.
COMPOSE="docker compose -f $REPO_ROOT/${COMPOSE_FILE:-docker-compose.prod.yml}"

[ -f "$DB_DUMP" ] || { echo "FATAL: $DB_DUMP not found" >&2; exit 1; }
[ -f "$STORAGE_ARCHIVE" ] || { echo "FATAL: $STORAGE_ARCHIVE not found" >&2; exit 1; }

# Override with e.g. ENV_FILE=backend/.env.rehearsal alongside COMPOSE_FILE above — production
# use should never set this (defaults to the real backend/.env).
ENV_FILE="$REPO_ROOT/${ENV_FILE:-backend/.env}"
DB_NAME="$(grep -E '^DB_DATABASE=' "$ENV_FILE" | cut -d= -f2-)"
DB_USER="$(grep -E '^DB_USERNAME=' "$ENV_FILE" | cut -d= -f2-)"

read -r -p "This will DROP and recreate '$DB_NAME' and overwrite backend/storage/app/private. Continue? [y/N] " CONFIRM
[ "$CONFIRM" = "y" ] || { echo "Aborted."; exit 1; }

echo "Putting the app into maintenance mode..."
$COMPOSE exec -T app php artisan down || true

echo "Dropping and recreating database '$DB_NAME'..."
$COMPOSE exec -T postgres psql -U "$DB_USER" -d postgres \
  -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();"
$COMPOSE exec -T postgres dropdb -U "$DB_USER" "$DB_NAME"
$COMPOSE exec -T postgres createdb -U "$DB_USER" "$DB_NAME"

echo "Restoring database from $DB_DUMP..."
$COMPOSE exec -T postgres pg_restore -U "$DB_USER" -d "$DB_NAME" --no-owner < "$DB_DUMP"

echo "Restoring storage from $STORAGE_ARCHIVE..."
rm -rf "$REPO_ROOT/backend/storage/app/private"
tar -xzf "$STORAGE_ARCHIVE" -C "$REPO_ROOT/backend/storage/app"

echo "Bringing the app back up..."
$COMPOSE exec -T app php artisan up

echo "Restore complete. Spot-check the app before considering this done — see" \
     "docs/deployment.md 'Restore procedure' for the verification checklist."

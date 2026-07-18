#!/bin/bash
# Nightly backup for DentalSuite production: Postgres (custom-format pg_dump, restorable with
# pg_restore) + the private file storage tree (patient attachments/imaging once that module
# exists). Run on the VPS host (not inside a container) via cron — see docs/deployment.md
# "Backup schedule". Assumes docker-compose.prod.yml is running from the repo root.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# Override with e.g. COMPOSE_FILE=docker-compose.yml to rehearse against a non-production stack
# (see docs/deployment.md "Testing restore") — production use should never set this.
COMPOSE="docker compose -f $REPO_ROOT/${COMPOSE_FILE:-docker-compose.prod.yml}"
BACKUP_ROOT="${DENTALSUITE_BACKUP_DIR:-/var/backups/dentalsuite}"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
RETENTION_DAYS="${DENTALSUITE_BACKUP_RETENTION_DAYS:-14}"

DB_NAME="$(grep -E '^DB_DATABASE=' "$REPO_ROOT/backend/.env" | cut -d= -f2-)"
DB_USER="$(grep -E '^DB_USERNAME=' "$REPO_ROOT/backend/.env" | cut -d= -f2-)"

mkdir -p "$BACKUP_ROOT/database" "$BACKUP_ROOT/storage"

echo "[$TIMESTAMP] Dumping database '$DB_NAME'..."
$COMPOSE exec -T postgres pg_dump -U "$DB_USER" -d "$DB_NAME" --format=custom \
  > "$BACKUP_ROOT/database/dentalsuite-$TIMESTAMP.dump"

echo "[$TIMESTAMP] Archiving private storage..."
tar -czf "$BACKUP_ROOT/storage/dentalsuite-storage-$TIMESTAMP.tar.gz" \
  -C "$REPO_ROOT/backend/storage/app" private

echo "[$TIMESTAMP] Pruning backups older than $RETENTION_DAYS days..."
find "$BACKUP_ROOT/database" -name '*.dump' -mtime "+$RETENTION_DAYS" -delete
find "$BACKUP_ROOT/storage" -name '*.tar.gz' -mtime "+$RETENTION_DAYS" -delete

echo "[$TIMESTAMP] Done: $BACKUP_ROOT/database/dentalsuite-$TIMESTAMP.dump"

# Offsite copy — activates automatically the moment BACKUP_S3_BUCKET is set (in the shell
# environment cron invokes this script with, e.g. /etc/environment or a sourced env file — do
# NOT put it in backend/.env, that file is for the application, not ops tooling). No code change
# needed to turn this on: provision any S3-compatible bucket (AWS S3, Backblaze B2, Wasabi,
# DigitalOcean Spaces, MinIO, ...), set the three variables below, done.
#
#   BACKUP_S3_BUCKET             required to enable offsite sync at all, e.g. "my-clinic-backups"
#   BACKUP_S3_ENDPOINT           only for non-AWS S3-compatible providers, e.g.
#                                 "https://s3.us-west-000.backblazeb2.com" — leave unset for AWS S3
#   AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / AWS_DEFAULT_REGION
#                                 standard AWS CLI credential env vars (works for AWS and for
#                                 S3-compatible providers using access-key auth) — deliberately
#                                 separate from the AWS_* vars in backend/.env, which are for the
#                                 application's own file-storage disk, not backup credentials.
#
# Requires the AWS CLI (`apt install awscli` or `pip install awscli`) on the backup host — see
# docs/deployment.md "Backup & Recovery" for the full setup checklist.
if [ -n "${BACKUP_S3_BUCKET:-}" ]; then
  echo "[$TIMESTAMP] Syncing $BACKUP_ROOT to s3://$BACKUP_S3_BUCKET/ ..."
  ENDPOINT_ARG=()
  [ -n "${BACKUP_S3_ENDPOINT:-}" ] && ENDPOINT_ARG=(--endpoint-url "$BACKUP_S3_ENDPOINT")
  aws s3 sync "$BACKUP_ROOT" "s3://$BACKUP_S3_BUCKET/dentalsuite/" "${ENDPOINT_ARG[@]}" --only-show-errors
  echo "[$TIMESTAMP] Offsite sync complete."
else
  echo "[$TIMESTAMP] WARNING: BACKUP_S3_BUCKET is not set — this backup exists on local disk" \
       "only and will NOT survive VPS loss or disk failure. See docs/deployment.md" \
       "'Backup & Recovery' to enable offsite sync." >&2
fi

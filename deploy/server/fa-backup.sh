#!/usr/bin/env bash
# Nightly backup: database dump + private files (receipts, photos), 14-day retention.
# Cron (root): 30 2 * * * bash /var/www/family-affairs/current/deploy/server/fa-backup.sh
set -euo pipefail
DATA_DIR="${DATA_DIR:-/var/lib/family-affairs}"
OUT="$DATA_DIR/backups"; STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$OUT"; umask 077
set -a; source <(grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' "$DATA_DIR/.env" | sed 's/"//g'); set +a
mysqldump --single-transaction --routines --no-tablespaces -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$OUT/db-$STAMP.sql.gz"
tar -czf "$OUT/files-$STAMP.tar.gz" -C "$DATA_DIR/storage/app" private
find "$OUT" -type f -mtime +14 -delete
echo "✓ backup $STAMP"

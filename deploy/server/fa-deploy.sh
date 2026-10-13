#!/usr/bin/env bash
# Server side: unpack a release, link shared data, migrate, cache, switch, restart worker.
# Usage (as root): bash fa-deploy.sh /tmp/family-affairs-deploy-<stamp>.tar.gz
#        bash fa-deploy.sh --rollback
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/family-affairs}"
DATA_DIR="${DATA_DIR:-/var/lib/family-affairs}"
PHP="${PHP_BIN:-php8.3}"
RUN_AS="www-data"

if [ "${1:-}" = "--rollback" ]; then
  PREV="$(ls -1d "$APP_DIR"/releases/* | sort | tail -n 2 | head -n 1)"
  ln -sfn "$PREV" "$APP_DIR/current"
  systemctl reload php8.3-fpm; systemctl restart family-affairs-queue
  echo "↺ Rolled back to $PREV (database migrations are NOT rolled back automatically)"; exit 0
fi

TARBALL="$1"; STAMP="$(date +%Y%m%d-%H%M%S)"; REL="$APP_DIR/releases/$STAMP"
mkdir -p "$REL" "$DATA_DIR"/{storage,backups}
tar -xzf "$TARBALL" -C "$REL" --strip-components=1

# First install: create shared storage layout and .env from the example.
if [ ! -f "$DATA_DIR/.env" ]; then
  cp "$REL/.env.example" "$DATA_DIR/.env"
  sed -i 's/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/; s/^SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=true/; s/^FA_MESSAGING_DRIVER=.*/FA_MESSAGING_DRIVER=nextsms/; s/^LOG_LEVEL=.*/LOG_LEVEL=warning/' "$DATA_DIR/.env"
  chmod 640 "$DATA_DIR/.env"; chown root:$RUN_AS "$DATA_DIR/.env"
  echo "⚠ Created $DATA_DIR/.env — set APP_URL, DB_*, NEXTSMS_* then re-run this script."; exit 1
fi
if [ ! -d "$DATA_DIR/storage/app" ]; then
  cp -a "$REL/storage/." "$DATA_DIR/storage/"
fi
mkdir -p "$DATA_DIR/storage/app/private" "$DATA_DIR/storage/framework/"{cache,sessions,views} "$DATA_DIR/storage/logs"
rm -rf "$REL/storage"; ln -s "$DATA_DIR/storage" "$REL/storage"
ln -sfn "$DATA_DIR/.env" "$REL/.env"
chown -R $RUN_AS:$RUN_AS "$DATA_DIR/storage" "$REL/bootstrap/cache"

cd "$REL"
grep -q '^APP_KEY=base64' .env || runuser -u $RUN_AS -- $PHP artisan key:generate --force
runuser -u $RUN_AS -- $PHP artisan down --retry=30 2>/dev/null || true
# Backup before migrating.
bash "$REL/deploy/server/fa-backup.sh" || echo "⚠ backup failed — continuing"
runuser -u $RUN_AS -- $PHP artisan migrate --force
# Structure only (roles, funds, unconfirmed rules, templates). Never demo data in production.
runuser -u $RUN_AS -- $PHP artisan db:seed --force
runuser -u $RUN_AS -- $PHP artisan optimize
ln -sfn "$REL" "$APP_DIR/current"
runuser -u $RUN_AS -- $PHP artisan up
systemctl reload php8.3-fpm
systemctl restart family-affairs-queue 2>/dev/null || echo "⚠ install deploy/server/family-affairs-queue.service"
# Keep the 5 newest releases.
ls -1d "$APP_DIR"/releases/* | sort | head -n -5 | xargs -r rm -rf
curl -fsS -o /dev/null "http://127.0.0.1/up" -H "Host: $(grep '^APP_URL' .env | sed 's#.*//##')" && echo "✓ Live: $REL" || echo "⚠ Health check failed — inspect: tail -n 50 $DATA_DIR/storage/logs/laravel-*.log"

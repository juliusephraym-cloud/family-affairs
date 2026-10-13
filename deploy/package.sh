#!/usr/bin/env bash
# Build a production tarball on the Mac: tests → composer (no dev, PHP 8.3) → vite build → tar.
# Usage: bash deploy/package.sh [--ship]
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$(pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$HOME/Documents/family-affairs-deploy-$STAMP.tar.gz"
[ -f deploy/deploy.conf ] && source deploy/deploy.conf

echo "▶ Running tests"
php artisan test --compact

echo "▶ Building assets"
npm ci --silent && npm run build

echo "▶ Staging release"
BUILD="$(mktemp -d)/family-affairs"
mkdir -p "$BUILD"
COPYFILE_DISABLE=1 tar --exclude '._*' --exclude '.git' --exclude 'node_modules' --exclude 'vendor' --exclude '.env' \
  --exclude 'storage/app/private/*' --exclude 'storage/logs/*' --exclude 'storage/framework/*/*' --exclude 'tests' \
  --exclude '.phpunit.cache' --exclude 'public/hot' --exclude 'deploy/deploy.conf' -cf - . | tar -xf - -C "$BUILD"
(cd "$BUILD" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

# Hygiene: no demo data, no secrets, no family records in the package.
grep -rqE "NEXTSMS_TOKEN=.+[A-Za-z0-9]" "$BUILD/.env.example" && { echo "✗ .env.example contains a token"; exit 1; }
[ -f "$BUILD/.env" ] && { echo "✗ .env must not be packaged"; exit 1; }

COPYFILE_DISABLE=1 tar --exclude '._*' -czf "$OUT" -C "$(dirname "$BUILD")" family-affairs
echo "✓ $OUT ($(du -h "$OUT" | cut -f1))"

if [ "${1:-}" = "--ship" ]; then
  [ -n "${SSH_TARGET:-}" ] || { echo "Set SSH_TARGET in deploy/deploy.conf"; exit 1; }
  scp "$OUT" deploy/server/fa-deploy.sh "$SSH_TARGET:/tmp/"
  echo "Now run on the server:"
  echo "ssh $SSH_TARGET 'bash /tmp/fa-deploy.sh /tmp/$(basename "$OUT")'"
fi

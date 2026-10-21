#!/usr/bin/env bash
# Release-based deploy: build a new release next to the current one, then switch the symlink.
# Usage (on the server, as the storesiphon user):  ./deploy.sh <git-ref>
set -euo pipefail

APP=/var/www/storesiphon
REF="${1:-main}"
RELEASE="$APP/releases/$(date +%Y%m%d%H%M%S)"

REPO_URL="${REPO_URL:?set REPO_URL to the git remote, e.g. git@github.com:you/storesiphon.git}"
git clone --depth 1 --branch "$REF" "$REPO_URL" "$RELEASE"

cd "$RELEASE"
ln -sfn "$APP/shared/.env" .env
rm -rf storage && ln -sfn "$APP/shared/storage" storage

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci && npm run build && rm -rf node_modules

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

ln -sfn "$RELEASE" "$APP/current"
sudo systemctl reload php8.3-fpm
php artisan queue:restart

# keep the five most recent releases
ls -1dt "$APP"/releases/* | tail -n +6 | xargs -r rm -rf

"$RELEASE/deploy/smoke-test.sh" "${APP_URL:-https://storesiphon.com}"

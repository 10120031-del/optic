#!/usr/bin/env bash
#
# Deploy Lucent Optics in place on a single server.
#
#   ./deploy.sh            # build, migrate, cache, restart workers
#   ./deploy.sh --pull     # also fast-forward the checkout first
#   ./deploy.sh --no-down  # skip maintenance mode (fine for asset-only changes)
#
# Run it as the user that owns the checkout — not root, or the caches it
# writes end up unreadable by php-fpm. See DEPLOYMENT.md for the server
# prerequisites (queue worker, cron, web root, permissions).

set -euo pipefail

cd "$(dirname "$0")"

PULL=false
MAINTENANCE=true

for arg in "$@"; do
    case "$arg" in
        --pull) PULL=true ;;
        --no-down) MAINTENANCE=false ;;
        -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 1 ;;
    esac
done

step() { printf '\n\033[1;36m==>\033[0m %s\n' "$1"; }
fail() { printf '\n\033[1;31mDeploy failed:\033[0m %s\n' "$1" >&2; exit 1; }

# --- Preflight ---------------------------------------------------------------
# Cheap checks first: a deploy that dies halfway through leaves the shop down.

[ -f .env ] || fail ".env is missing. Copy .env.production.example and fill it in."

grep -q '^APP_KEY=.\+' .env || fail "APP_KEY is empty. Run: php artisan key:generate"

if ! grep -q '^APP_ENV=production' .env; then
    echo "Warning: APP_ENV is not 'production' in .env."
fi

if grep -q '^APP_DEBUG=true' .env; then
    fail "APP_DEBUG=true would print stack traces (and credentials) to customers."
fi

command -v php >/dev/null || fail "php is not on PATH."
command -v composer >/dev/null || fail "composer is not on PATH."
command -v npm >/dev/null || fail "npm is not on PATH."

# --- Bring the shop down (and guarantee it comes back) -----------------------

if [ "$MAINTENANCE" = true ]; then
    step "Enabling maintenance mode"
    php artisan down --render="errors::503" --retry=15 || true
    trap 'php artisan up >/dev/null 2>&1 || true' EXIT
fi

if [ "$PULL" = true ]; then
    step "Fetching latest code"
    git pull --ff-only
fi

# --- Build -------------------------------------------------------------------

step "Installing PHP dependencies (production)"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

step "Installing JS dependencies and building assets"
# `npm ci` is the reproducible install; postinstall also stages the MediaPipe
# WASM runtime and face-landmarker model into public/mediapipe.
npm ci
npm run build

# --- Database ----------------------------------------------------------------

step "Running migrations"
php artisan migrate --force

step "Seeding reference data (face shapes, lens features)"
php artisan db:seed --class=ProductionSeeder --force

# --- Wiring ------------------------------------------------------------------

step "Linking public storage"
php artisan storage:link 2>/dev/null || true

step "Caching config, routes, views and events"
# `optimize` covers config:cache, event:cache, route:cache and view:cache.
php artisan optimize:clear
php artisan optimize

# --- Workers -----------------------------------------------------------------

step "Restarting queue workers"
# Signals the running workers to exit after their current job; supervisor (or
# systemd) starts fresh ones that have the new code loaded. Without this,
# workers keep running the previous release indefinitely.
php artisan queue:restart

# --- Back up -----------------------------------------------------------------

if [ "$MAINTENANCE" = true ]; then
    step "Bringing the shop back online"
    trap - EXIT
    php artisan up
fi

step "Deploy complete"
php artisan about --only=environment,cache,drivers

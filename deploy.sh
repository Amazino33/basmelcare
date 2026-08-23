#!/bin/bash
#
# BasmelCare deployment.
#
# This repository holds TWO Laravel applications:
#   .           the public site
#   public/app  the staff app
#
# They share one git repository, so a single pull updates both, but each has
# its own composer.json, database migrations and caches — which is why every
# step below runs against both. The previous root script only migrated the
# public site, so staff-app migrations had to be run by hand.
#
# Front-end assets are NOT built here: public/build is committed to git, so
# run `npm run build` in whichever app you changed and commit the result
# BEFORE deploying. The server needs no node.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APPS=("$ROOT" "$ROOT/public/app")

step() { printf '\n==> %s\n' "$1"; }

# A failed deploy must not silently go live: a half-applied migration is worse
# than a maintenance page. Leave both apps down and say exactly how to recover.
on_failure() {
    printf '\n!! DEPLOY FAILED — both apps are still in maintenance mode.\n'
    printf '   Fix the problem and re-run ./deploy.sh, or bring them up manually:\n'
    for app in "${APPS[@]}"; do
        printf '     (cd %s && php artisan up)\n' "$app"
    done
}
trap on_failure ERR

# ── 0. Pre-flight ────────────────────────────────────────────────────
# The staff app writes product images into the public site's storage and
# builds their URLs from PUBLIC_SITE_URL. .env is not in git, so a server
# that has never been told the shop's address would fall back to localhost
# and serve broken images. Catch that before anything goes offline.
step "Checking configuration"
if ! grep -qE '^PUBLIC_SITE_URL=.+' "$ROOT/public/app/.env"; then
    printf '
!! PUBLIC_SITE_URL is not set in public/app/.env
'
    printf '   Add the shop address, e.g.
'
    printf '     PUBLIC_SITE_URL=https://basmelcare.com
'
    printf '   Nothing has been changed. Re-run ./deploy.sh afterwards.

'
    exit 1
fi
echo "    PUBLIC_SITE_URL: $(grep -E '^PUBLIC_SITE_URL=' "$ROOT/public/app/.env" | cut -d= -f2-)"

# ── 1. Maintenance mode ──────────────────────────────────────────────
step "Taking both apps offline"
for app in "${APPS[@]}"; do
    (cd "$app" && php artisan down --retry=60) || true
done

# ── 2. Pull (one repo covers both apps) ──────────────────────────────
step "Pulling latest code"
cd "$ROOT"
git pull

# ── 3. Per-app deploy ────────────────────────────────────────────────
for app in "${APPS[@]}"; do
    name="$([ "$app" = "$ROOT" ] && echo 'public site' || echo 'staff app')"
    cd "$app"

    step "[$name] Installing dependencies"
    php -d memory_limit=-1 "$(which composer)" install \
        --no-dev --optimize-autoloader --no-interaction

    step "[$name] Running migrations"
    php artisan migrate --force

    step "[$name] Ensuring storage symlink"
    if [ ! -L "$app/public/storage" ]; then
        ln -s "$app/storage/app/public" "$app/public/storage"
        echo "    symlink created"
    else
        echo "    symlink already present"
    fi

    step "[$name] Rebuilding caches"
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
done

# ── 3b. Relocate product images ──────────────────────────────────────
# Images uploaded before the shared disk existed are sitting in the staff
# app's own storage, where the shop cannot serve them. This copies them
# across; it is a no-op once everything is in place, so it is safe to run
# on every deploy.
step "[staff app] Moving product images into the public site storage"
(cd "$ROOT/public/app" && php artisan products:move-images)

# ── 4. Back online ───────────────────────────────────────────────────
trap - ERR
step "Bringing both apps back online"
for app in "${APPS[@]}"; do
    (cd "$app" && php artisan up)
done

printf '\n✅ BasmelCare deployed — public site and staff app.\n'

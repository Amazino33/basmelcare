#!/bin/bash
set -e

# 1. Maintenance Mode
php artisan down || true

# 2. Pull Changes
git fetch origin master
git reset --hard origin/master

# 3. Install Dependencies
php -d memory_limit=-1 $(which composer) install --no-dev --optimize-autoloader --no-interaction

# 4. Migrate Database
php artisan migrate --force

# 5. Storage symlink
STORAGE_LINK="$(pwd)/public/storage"
if [ ! -L "$STORAGE_LINK" ]; then
    ln -s "$(pwd)/storage/app/public" "$STORAGE_LINK"
    echo "Symlink created."
else
    echo "Symlink already exists."
fi

# 6. Clear and rebuild caches
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Live
php artisan up

echo "✅ BasmelCare Deployment Success!"

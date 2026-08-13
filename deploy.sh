#!/bin/bash
set -e

echo "==> Pulling latest code..."
git pull

echo "==> Clearing main site caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "==> Clearing staff app caches..."
cd public/app
php artisan config:clear
php artisan route:clear
php artisan view:clear
cd ../..

echo "==> Done."

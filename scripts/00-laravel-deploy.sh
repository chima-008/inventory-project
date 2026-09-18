#!/usr/bin/env bash

set -e

echo "Installing Composer dependencies..."

composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

echo "Caching Laravel configuration..."

php artisan config:cache

echo "Caching Laravel routes..."

php artisan route:cache

echo "Creating storage link..."

php artisan storage:link || true

echo "Laravel deployment preparation complete."
#!/bin/bash

set -e

APP_DIR="/home/u776774917/domains/mooreliferesort.com/Hotelms"
PHP_BIN="/usr/bin/php"

cd "$APP_DIR"

echo "Starting deployment..."

git pull origin main

if command -v composer >/dev/null 2>&1; then
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
else
    echo "Composer not found globally. Skipping composer install."
fi

$PHP_BIN artisan migrate --force
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan queue:restart || true

echo "Done."
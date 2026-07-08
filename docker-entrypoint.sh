#!/bin/sh
set -e

DB_PATH="${DB_DATABASE:-/var/www/database/narrv.sqlite}"
DB_DIR="$(dirname "$DB_PATH")"

mkdir -p \
  "$DB_DIR" \
  /var/www/storage/app/transcripts \
  /var/www/storage/framework/cache/data \
  /var/www/storage/framework/sessions \
  /var/www/storage/framework/views \
  /var/www/storage/logs \
  /var/www/bootstrap/cache

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ "$DB_PATH" != ":memory:" ]; then
  touch "$DB_PATH"
fi

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache "$DB_DIR"
chmod -R 775 /var/www/storage /var/www/bootstrap/cache "$DB_DIR"

php artisan migrate --force

exec "$@"

#!/bin/sh
set -e

DB_PATH="${DB_DATABASE:-/var/www/database/narrv.sqlite}"
DB_DIR="$(dirname "$DB_PATH")"
YOUTUBE_COOKIES_FILE="${YOUTUBE_COOKIES_PATH:-/var/www/storage/app/youtube-cookies.txt}"

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

if [ -n "${YOUTUBE_COOKIES_BASE64:-}" ]; then
  mkdir -p "$(dirname "$YOUTUBE_COOKIES_FILE")"

  if ! printf '%s' "$YOUTUBE_COOKIES_BASE64" | base64 -d > "$YOUTUBE_COOKIES_FILE"; then
    echo "Unable to decode YOUTUBE_COOKIES_BASE64 into $YOUTUBE_COOKIES_FILE" >&2
    exit 1
  fi

  chmod 600 "$YOUTUBE_COOKIES_FILE"
fi

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache "$DB_DIR"
chmod -R 775 /var/www/storage /var/www/bootstrap/cache "$DB_DIR"

if [ -n "${YOUTUBE_COOKIES_BASE64:-}" ]; then
  chown www-data:www-data "$YOUTUBE_COOKIES_FILE"
  chmod 600 "$YOUTUBE_COOKIES_FILE"
fi

php artisan migrate --force

exec "$@"

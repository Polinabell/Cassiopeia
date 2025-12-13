#!/usr/bin/env bash
set -e

APP_DIR="/var/www/html"
PATCH_DIR="/opt/laravel-patches"

echo "[php] init start"

if [ ! -f "$APP_DIR/artisan" ]; then
  echo "[php] creating laravel skeleton"
  composer create-project --no-interaction --prefer-dist laravel/laravel:^11 "$APP_DIR"
  cp "$APP_DIR/.env.example" "$APP_DIR/.env" || true
  sed -i 's|APP_NAME=Laravel|APP_NAME=ISSOSDR|g' "$APP_DIR/.env" || true
  php "$APP_DIR/artisan" key:generate || true
fi

# Установка дополнительных пакетов
cd "$APP_DIR"
if ! composer show phpoffice/phpspreadsheet >/dev/null 2>&1; then
  echo "[php] installing phpspreadsheet for XLSX export"
  composer require --no-interaction phpoffice/phpspreadsheet
fi
if ! composer show predis/predis >/dev/null 2>&1; then
  echo "[php] installing predis for Redis support"
  composer require --no-interaction predis/predis
fi

if [ -d "$PATCH_DIR" ]; then
  echo "[php] applying patches"
  rsync -a "$PATCH_DIR/" "$APP_DIR/"
fi

# Обновляем .env для Redis если заданы переменные
if [ -n "$REDIS_HOST" ]; then
  sed -i "s|^REDIS_HOST=.*|REDIS_HOST=${REDIS_HOST}|g" "$APP_DIR/.env" 2>/dev/null || true
  sed -i "s|^CACHE_DRIVER=.*|CACHE_DRIVER=redis|g" "$APP_DIR/.env" 2>/dev/null || true
  sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=redis|g" "$APP_DIR/.env" 2>/dev/null || true
fi

chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true

echo "[php] starting php-fpm"
php-fpm -F

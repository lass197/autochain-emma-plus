#!/usr/bin/env bash
set -e

echo "==> Autochain Emma+ deploy script"

cd /var/www/html

# APP_URL depuis Render si non défini
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
  echo "APP_URL=$APP_URL"
fi

# APP_KEY Laravel (base64) si absente ou invalide
if [ -z "$APP_KEY" ] || [[ "$APP_KEY" != base64:* ]]; then
  export APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  echo "APP_KEY générée pour ce conteneur (définis APP_KEY dans Render pour la persister)."
fi

echo "==> Composer install"
composer install --no-dev --optimize-autoloader --no-interaction --working-dir=/var/www/html

echo "==> Permissions storage"
chmod -R ug+rwx storage bootstrap/cache || true

echo "==> Storage link"
php artisan storage:link || true

echo "==> Migrations"
php artisan migrate --force

if [ "$SEED_ON_DEPLOY" = "true" ]; then
  echo "==> Seed (comptes démo)"
  php artisan db:seed --force || echo "Seed ignoré (données déjà présentes)."
fi

echo "==> Caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Deploy prêt"

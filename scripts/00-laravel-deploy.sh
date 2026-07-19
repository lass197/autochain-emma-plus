#!/usr/bin/env bash
# Ne pas utiliser set -e : le serveur doit démarrer même si migrate échoue,
# sinon le healthcheck Render timeout.

echo "==> Autochain Emma+ boot"
cd /var/www/html

export PORT="${PORT:-10000}"
echo "PORT=$PORT"

if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
fi
echo "APP_URL=${APP_URL:-unset}"

if [ -z "$APP_KEY" ] || [[ "$APP_KEY" != base64:* ]]; then
  export APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  echo "APP_KEY générée pour ce boot"
fi

# Persiste les vars critiques pour les workers php-fpm
cat > /var/www/html/.env <<EOF
APP_NAME="${APP_NAME:-Autochain Emma+}"
APP_ENV="${APP_ENV:-production}"
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}
LOG_CHANNEL=stderr
DB_CONNECTION=${DB_CONNECTION:-pgsql}
DATABASE_URL=${DATABASE_URL:-}
SESSION_DRIVER=${SESSION_DRIVER:-cookie}
CACHE_STORE=${CACHE_STORE:-file}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}
FILESYSTEM_DISK=local
AUTOCHAIN_ALLOW_SIMULATE_FALLBACK=true
EOF

chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
php artisan storage:link 2>/dev/null || true

if [ -z "$DATABASE_URL" ]; then
  echo "WARN: DATABASE_URL vide — définis-la dans Render (Internal URL de ta Postgres)."
else
  echo "==> Migrations"
  php artisan migrate --force 2>&1 || echo "WARN: migrate a échoué"

  if [ "$SEED_ON_DEPLOY" = "true" ]; then
    echo "==> Seed"
    php artisan db:seed --force 2>&1 || echo "WARN: seed ignoré"
  fi
fi

echo "==> Caches"
php artisan config:clear 2>/dev/null || true
php artisan config:cache 2>&1 || true
php artisan route:cache 2>&1 || true
php artisan view:cache 2>&1 || true

echo "==> Boot prêt (nginx sur PORT=$PORT)"

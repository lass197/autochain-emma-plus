#!/usr/bin/env bash
set -u

cd /var/www/html

PORT="${PORT:-10000}"
echo "==> Autochain Emma+ | PHP $(php -r 'echo PHP_VERSION;') | PORT=${PORT}"

# Apache sur le port Render
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf
echo "ServerName localhost" >> /etc/apache2/apache2.conf

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
fi
export APP_URL="${APP_URL:-https://autochain-emma-plus.onrender.com}"

if [ -z "${APP_KEY:-}" ] || [[ "${APP_KEY}" != base64:* ]]; then
  export APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  echo "==> APP_KEY générée pour ce boot"
fi

# Postgres si DATABASE_URL fourni, sinon SQLite (toujours démarrable)
if [ -n "${DATABASE_URL:-}" ]; then
  export DB_CONNECTION=pgsql
  echo "==> DB: PostgreSQL (DATABASE_URL)"
else
  export DB_CONNECTION=sqlite
  export DB_DATABASE=/var/www/html/database/database.sqlite
  touch "${DB_DATABASE}"
  chmod 664 "${DB_DATABASE}" || true
  echo "==> DB: SQLite fallback (DATABASE_URL absent)"
fi

export SESSION_DRIVER="${SESSION_DRIVER:-cookie}"
export CACHE_STORE="${CACHE_STORE:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
export APP_ENV=production
export APP_DEBUG=false

# .env pour Artisan / PHP-FPM
{
  echo "APP_NAME=\"${APP_NAME:-Autochain Emma+}\""
  echo "APP_ENV=production"
  echo "APP_KEY=${APP_KEY}"
  echo "APP_DEBUG=false"
  echo "APP_URL=${APP_URL}"
  echo "LOG_CHANNEL=stderr"
  echo "DB_CONNECTION=${DB_CONNECTION}"
  if [ "${DB_CONNECTION}" = "sqlite" ]; then
    echo "DB_DATABASE=${DB_DATABASE}"
  else
    echo "DATABASE_URL=${DATABASE_URL}"
  fi
  echo "SESSION_DRIVER=${SESSION_DRIVER}"
  echo "CACHE_STORE=${CACHE_STORE}"
  echo "QUEUE_CONNECTION=${QUEUE_CONNECTION}"
  echo "FILESYSTEM_DISK=local"
  echo "AUTOCHAIN_ALLOW_SIMULATE_FALLBACK=true"
} > /var/www/html/.env

chmod -R ug+rwx storage bootstrap/cache database || true
php artisan storage:link 2>/dev/null || true

# Pas de config:cache avant migrate (évite un cache pourri)
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

echo "==> migrate"
php artisan migrate --force 2>&1 || echo "WARN: migrate failed"

if [ "${SEED_ON_DEPLOY:-true}" = "true" ]; then
  echo "==> seed"
  php artisan db:seed --force 2>&1 || echo "WARN: seed skipped"
fi

echo "==> Apache on :${PORT} (health: /health.php)"
exec apache2-foreground

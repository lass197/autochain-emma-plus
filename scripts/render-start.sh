#!/usr/bin/env bash
set -u

cd /var/www/html

PORT="${PORT:-10000}"
echo "==> Autochain Emma+ | PHP $(php -v | head -n1) | PORT=${PORT}"

# Apache écoute le port fourni par Render
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
fi

if [ -z "${APP_KEY:-}" ] || [[ "${APP_KEY}" != base64:* ]]; then
  export APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  echo "==> APP_KEY générée pour ce boot"
fi

export SESSION_DRIVER="${SESSION_DRIVER:-cookie}"
export CACHE_STORE="${CACHE_STORE:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

cat > /var/www/html/.env <<EOF
APP_NAME="${APP_NAME:-Autochain Emma+}"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=${APP_URL:-https://autochain-emma-plus.onrender.com}
LOG_CHANNEL=stderr
DB_CONNECTION=${DB_CONNECTION}
DATABASE_URL=${DATABASE_URL:-}
SESSION_DRIVER=${SESSION_DRIVER}
CACHE_STORE=${CACHE_STORE}
QUEUE_CONNECTION=${QUEUE_CONNECTION}
FILESYSTEM_DISK=local
AUTOCHAIN_ALLOW_SIMULATE_FALLBACK=true
EOF

chmod -R ug+rwx storage bootstrap/cache || true
php artisan storage:link 2>/dev/null || true

if [ -n "${DATABASE_URL:-}" ]; then
  echo "==> migrate"
  php artisan migrate --force || echo "WARN: migrate failed"
  if [ "${SEED_ON_DEPLOY:-true}" = "true" ]; then
    php artisan db:seed --force || echo "WARN: seed skipped"
  fi
else
  echo "WARN: DATABASE_URL manquant"
fi

php artisan config:clear || true
php artisan config:cache || true

echo "==> Apache start on :${PORT}"
exec apache2-foreground

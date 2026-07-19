#!/usr/bin/env bash
set -u

cd /var/www/html

PORT="${PORT:-10000}"
echo "==> Autochain Emma+ | PHP $(php -r 'echo PHP_VERSION;') | PORT=${PORT}"

sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf
grep -q "ServerName localhost" /etc/apache2/apache2.conf || echo "ServerName localhost" >> /etc/apache2/apache2.conf

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
fi
export APP_URL="${APP_URL:-https://autochain-emma-plus.onrender.com}"

if [ -z "${APP_KEY:-}" ] || [[ "${APP_KEY}" != base64:* ]]; then
  export APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  echo "==> APP_KEY générée pour ce boot"
fi

if [ -n "${DATABASE_URL:-}" ]; then
  export DB_CONNECTION=pgsql
  # Laravel 13 lit DB_URL (pas DATABASE_URL)
  export DB_URL="${DATABASE_URL}"
  echo "==> DB: PostgreSQL"
else
  export DB_CONNECTION=sqlite
  export DB_DATABASE=/var/www/html/database/database.sqlite
  unset DB_URL || true
  touch "${DB_DATABASE}"
  chmod 664 "${DB_DATABASE}" || true
  echo "==> DB: SQLite fallback"
fi

export SESSION_DRIVER="${SESSION_DRIVER:-cookie}"
export CACHE_STORE="${CACHE_STORE:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export LOG_CHANNEL=stderr
export APP_ENV=production
# true temporairement pour voir les erreurs en prod Render
export APP_DEBUG="${APP_DEBUG:-false}"

cat > /var/www/html/.env <<EOF
APP_NAME="Autochain Emma+"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG}
APP_URL=${APP_URL}
LOG_CHANNEL=stderr
LOG_LEVEL=debug
DB_CONNECTION=${DB_CONNECTION}
DB_DATABASE=${DB_DATABASE:-}
DB_URL=${DB_URL:-}
SESSION_DRIVER=${SESSION_DRIVER}
CACHE_STORE=${CACHE_STORE}
QUEUE_CONNECTION=${QUEUE_CONNECTION}
FILESYSTEM_DISK=local
AUTOCHAIN_ALLOW_SIMULATE_FALLBACK=true
EOF

# Vérifie le build Vite
if [ ! -f public/build/manifest.json ]; then
  echo "ERROR: public/build/manifest.json manquant"
  ls -la public/build || true
else
  echo "==> Vite manifest OK"
fi

chmod -R ug+rwx storage bootstrap/cache database || true
chown -R www-data:www-data storage bootstrap/cache database || true
php artisan storage:link 2>/dev/null || true

php artisan optimize:clear 2>/dev/null || true

echo "==> migrate"
php artisan migrate --force 2>&1 || echo "WARN: migrate failed"

if [ "${SEED_ON_DEPLOY:-true}" = "true" ]; then
  echo "==> seed"
  php artisan db:seed --force 2>&1 || echo "WARN: seed skipped"
fi

echo "==> Apache on :${PORT}"
exec apache2-foreground

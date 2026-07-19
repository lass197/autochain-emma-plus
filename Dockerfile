# ---------- Frontend ----------
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
# Build assets (sans plugin fonts Bunny — plus fiable sur Render)
RUN npm run build && test -f public/build/manifest.json

# ---------- Vendor PHP (code only ; extensions installées au runtime) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --ignore-platform-reqs

# ---------- Runtime PHP 8.3 + Apache ----------
FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libgmp-dev \
        libpq-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
        git \
        curl \
        pkg-config \
    && docker-php-ext-install gmp pdo_pgsql pdo_sqlite opcache zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/composer.json /app/composer.lock ./
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x scripts/render-start.sh \
    && php artisan package:discover --ansi || true

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV PORT=10000

EXPOSE 10000

CMD ["bash", "scripts/render-start.sh"]

# ---------- Frontend ----------
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

# ---------- Vendor PHP ----------
FROM composer:2 AS vendor
WORKDIR /app
RUN apk add --no-cache gmp-dev $PHPIZE_DEPS \
    && docker-php-ext-install gmp
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --ignore-platform-req=ext-pdo_pgsql \
    --ignore-platform-req=ext-pcntl

# ---------- Runtime PHP 8.3 + Apache ----------
FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libgmp-dev \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-install gmp pdo_pgsql opcache zip \
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

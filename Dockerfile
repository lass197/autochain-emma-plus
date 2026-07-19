# Stage 1 — build frontend Vite/React
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

# Stage 2 — Laravel + Nginx + PHP-FPM
FROM richarvey/nginx-php-fpm:3.1.6

# gmp (Web3) + pgsql
RUN apk add --no-cache gmp gmp-dev postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install gmp pdo_pgsql \
    && apk del --no-network gmp-dev postgresql-dev $PHPIZE_DEPS

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && rm -rf /root/.composer/cache

COPY . /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

RUN composer dump-autoload --optimize --no-interaction \
    && chmod +x /var/www/html/scripts/*.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

# richarvey/nginx-php-fpm — Render injecte PORT=10000
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PORT=10000

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

CMD ["/start.sh"]

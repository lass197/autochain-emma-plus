# Stage 1 — build frontend Vite/React
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

# Stage 2 — Laravel + Nginx + PHP-FPM (image officielle Render)
FROM richarvey/nginx-php-fpm:3.1.6

# ext-gmp requis par simplito/elliptic-php (Web3)
RUN apk add --no-cache gmp gmp-dev $PHPIZE_DEPS \
    && docker-php-ext-install gmp \
    && apk del --no-network gmp-dev $PHPIZE_DEPS

COPY . /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

RUN chmod +x /var/www/html/scripts/*.sh \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

# Image config (richarvey/nginx-php-fpm)
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV COMPOSER_ALLOW_SUPERUSER=1

# Laravel defaults
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

WORKDIR /var/www/html

CMD ["/start.sh"]

# --- Build dependencies with Composer in a separate stage
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

# --- Runtime: PHP-FPM + Nginx on Alpine (SQLite >= 3.45)
FROM php:8.3-fpm-alpine3.20

# Runtime packages (nginx, sqlite libs, and build deps for PHP extensions)
RUN apk add --no-cache \
    nginx curl bash icu-dev libjpeg-turbo-dev libpng-dev libwebp-dev freetype-dev \
    libzip-dev oniguruma-dev sqlite sqlite-libs

# PHP extensions: gd, intl, opcache, zip, pdo_sqlite
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j$(nproc) gd intl opcache zip pdo_sqlite

# PHP-FPM listen on 127.0.0.1:9000
RUN sed -i 's|^listen = .*$|listen = 127.0.0.1:9000|' /usr/local/etc/php-fpm.d/www.conf \
 && sed -i 's|^;clear_env = no$|clear_env = no|' /usr/local/etc/php-fpm.d/www.conf

# Nginx config
COPY ops/nginx.conf /etc/nginx/nginx.conf
COPY ops/drupal.conf /etc/nginx/conf.d/default.conf

# App code
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Persist data on /data; link public files there
RUN mkdir -p /data/db /data/files /data/private /data/config/sync \
 && rm -rf web/sites/default/files \
 && ln -s /data/files web/sites/default/files \
 && chown -R www-data:www-data /var/www/html /data

# Simple entrypoint to start both services
COPY ops/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
CMD ["/entrypoint.sh"]

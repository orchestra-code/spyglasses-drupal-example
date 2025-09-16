# PHP-FPM with Alpine (SQLite >= 3.45) + Nginx
FROM php:8.3-fpm-alpine3.20

# --- Runtime packages (stay installed)
RUN set -eux; apk add --no-cache \
  nginx curl bash \
  sqlite sqlite-libs \
  icu-libs libjpeg-turbo libpng libwebp freetype libzip oniguruma

# --- Build deps (removed after compiling PHP extensions)
RUN set -eux; \
  apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    sqlite-dev \
    icu-dev libjpeg-turbo-dev libpng-dev libwebp-dev freetype-dev libzip-dev oniguruma-dev; \
  docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
  docker-php-ext-install -j"$(nproc)" gd intl opcache zip mbstring pdo_sqlite; \
  apk del .build-deps; \
  php -m | sort

# Make PHP-FPM listen on TCP (nginx will proxy to it)
RUN sed -i 's|^listen = .*$|listen = 0.0.0.0:9000|' /usr/local/etc/php-fpm.d/www.conf \
 && sed -i 's|^;clear_env = no$|clear_env = no|' /usr/local/etc/php-fpm.d/www.conf

# Bring in Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# App root
WORKDIR /var/www/html

# Leverage Docker layer caching: copy composer files first
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

# Now copy the rest of your project (web/, modules/, etc)
COPY . .

# Nginx config + entrypoint (same files you already had)
COPY ops/nginx.conf /etc/nginx/nginx.conf
COPY ops/drupal.conf /etc/nginx/conf.d/default.conf
COPY ops/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Persist DB + files on a Fly volume mounted at /data
RUN mkdir -p /data/db /data/files /data/private /data/config/sync \
 && rm -rf web/sites/default/files \
 && ln -s /data/files web/sites/default/files \
 && chown -R www-data:www-data /var/www/html /data

EXPOSE 8080
CMD ["/entrypoint.sh"]

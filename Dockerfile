FROM php:8.3-apache-trixie

# Install required packages and PHP extensions
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    sqlite3 \
    libsqlite3-dev \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) gd intl opcache zip mbstring pdo_sqlite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure Apache
RUN a2enmod rewrite headers \
    && sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf \
    && sed -i 's|/var/www/html|/var/www/html/web|g' /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application files
COPY . .

RUN chmod 444 web/sites/default/settings.php

# Set up data directories and permissions
RUN mkdir -p /data/files /data/private /data/config/sync \
    && chown -R www-data:www-data /data \
    && rm -rf web/sites/default/files \
    && ln -s /data/files web/sites/default/files \
    && chown -h www-data:www-data web/sites/default/files \
    && chmod 755 /data/files \
    && chown -R www-data:www-data /var/www/html

# Apache configuration for Drupal
RUN echo '<Directory /var/www/html/web>' > /etc/apache2/conf-available/drupal.conf \
    && echo '  AllowOverride All' >> /etc/apache2/conf-available/drupal.conf \
    && echo '  Require all granted' >> /etc/apache2/conf-available/drupal.conf \
    && echo '</Directory>' >> /etc/apache2/conf-available/drupal.conf \
    && a2enconf drupal

# Create startup script to ensure permissions on mounted volume
RUN echo '#!/bin/bash' > /startup.sh \
    && echo 'mkdir -p /data/files /data/private /data/config/sync' >> /startup.sh \
    && echo 'chown -R www-data:www-data /data' >> /startup.sh \
    && echo 'chmod -R 755 /data/files' >> /startup.sh \
    && echo 'exec apache2-foreground' >> /startup.sh \
    && chmod +x /startup.sh

EXPOSE 8080
CMD ["/startup.sh"]
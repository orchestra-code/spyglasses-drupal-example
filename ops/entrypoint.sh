#!/usr/bin/env sh
set -e

# Ensure symlink target exists & permissions are correct after restarts
mkdir -p /data/files /data/db /data/private /data/config/sync
chown -R www-data:www-data /data /var/www/html

# Start PHP-FPM (daemonized) and Nginx (foreground)
php-fpm -D
exec nginx -g 'daemon off;'

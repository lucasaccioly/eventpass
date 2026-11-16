#!/bin/sh
set -e
cd /app

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

exec supervisord -c /app/docker/supervisord.conf

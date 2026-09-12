#!/bin/sh
set -eu

php artisan config:cache
php artisan route:cache

if [ -d resources/views ]; then
    php artisan view:cache
fi

exec "$@"

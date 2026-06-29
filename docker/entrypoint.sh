#!/bin/sh
set -e

if [ "$1" = "artisan" ]; then
    shift
    exec php artisan "$@"
fi

role=${1:-web}

if [ ! -f "artisan" ]; then
    echo "ERROR: artisan not found."
    exit 1
fi

if ! [ -L public/storage ]; then
    php artisan storage:link --no-interaction 2>/dev/null || true
fi

case "$role" in
    web)
        echo "Starting PHP-FPM..."
        exec php-fpm
        ;;
    worker)
        echo "Starting queue worker..."
        exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
        ;;
    scheduler)
        echo "Starting scheduler..."
        exec php artisan schedule:work
        ;;
    *)
        echo "Unknown role: $role"
        echo "Valid roles: web, worker, scheduler, artisan <command>"
        exit 1
        ;;
esac

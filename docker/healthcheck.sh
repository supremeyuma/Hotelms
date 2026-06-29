#!/bin/sh
set -e

role=${CONTAINER_ROLE:-web}

case "$role" in
    web)
        php -r "fsockopen('127.0.0.1', 9000) ? exit(0) : exit(1);"
        ;;
    worker)
        php artisan queue:monitor --no-interaction --quiet 2>/dev/null || exit 1
        ;;
    scheduler)
        php artisan schedule:list --no-interaction --quiet 2>/dev/null || exit 1
        ;;
    *)
        exit 0
        ;;
esac

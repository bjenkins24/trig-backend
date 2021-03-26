#!/usr/bin/env bash
set -e # exit script if any command fails (non-zero value)

role=${CONTAINER_ROLE:-app}

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan elastic:migrate 2021_03_03_053247_screenshot_fields --force

if [ "$role" = "app" ]; then

    php-fpm -F -R

elif [ "$role" = "queue" ]; then

    supervisord -c /etc/supervisor/supervisord.conf
    supervisorctl reread
    supervisorctl update
    supervisorctl start laravel-worker:*
    php-fpm -F -R

elif [ "$role" = "scheduler" ]; then

    while [ true ]
    do
      php /var/www/html/artisan schedule:run --verbose --no-interaction &
      sleep 60
    done

else
    echo "Could not match the container role \"$role\""
    exit 1
fi


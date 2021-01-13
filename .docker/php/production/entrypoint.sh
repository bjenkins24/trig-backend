#!/usr/bin/env bash
set -e # exit script if any command fails (non-zero value)

role=${CONTAINER_ROLE:-app}

#echo Waiting for redis service start...;
#
#while ! nc -z redis 6379;
#do
#sleep 1;
#done;
#
#echo Waiting for mysql service start...;
#
#while ! nc -z mysql 3306;
#do
#sleep 1;
#done;
#
#echo Waiting for elasticsearch to start...;
#
#while ! nc -z elasticsearch 9200;
#do
#sleep 1
#done;

#php artisan migrate --force
#php artisan elastic:migrate
#php artisan optimize

if [ "$role" = "app" ]; then

    php-fpm -F -R

elif [ "$role" = "queue" ]; then

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


#!/usr/bin/env bash
set -e # exit script if any command fails (non-zero value)

echo Waiting for redis service start...;

while ! nc -z redis 6379;
do
sleep 1;
done;

echo Waiting for mysql service start...;

while ! nc -z mysql 3306;
do
sleep 1;
done;

echo Waiting for elasticsearch to start...;

while ! nc -z elasticsearch 9200;
do
sleep 1
done;

#php artisan app:scaffold
php artisan migrate --force
php artisan elastic:migrate --force
php artisan passport:install --force
# php artisan horizon
# php artisan schedule:run

php-fpm -F -R

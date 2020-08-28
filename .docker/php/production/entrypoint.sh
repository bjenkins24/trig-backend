#!/usr/bin/env bash
set -e # exit script if any command fails (non-zero value)

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

php artisan migrate --force
php artisan elastic:migrate
php artisan config:cache

php-fpm -F -R
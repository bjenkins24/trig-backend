FROM php:7.4-fpm
USER root
SHELL ["/bin/bash", "-c"]
ARG BUILD_ENVIRONMENT
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends apt-utils git unzip netcat

RUN docker-php-ext-install pcntl bcmath mysqli pdo pdo_mysql

RUN yes "" | pecl install redis \
   && rm -rf /tmp/pear \
   && docker-php-ext-enable redis

COPY .docker/php/development/entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

# syntax = docker/dockerfile:1.0-experimental

FROM php:7.4-fpm
USER root
SHELL ["/bin/bash", "-c"]
ARG NEW_RELIC_AGENT_VERSION
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends apt-utils git unzip netcat awscli supervisor libpng-dev libonig-dev libjpeg-dev libfreetype6-dev libwebp-dev libxpm-dev zlib1g

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-xpm --with-webp

RUN docker-php-ext-install pcntl bcmath mysqli pdo pdo_mysql sockets gd

RUN yes "" | pecl install redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

COPY .docker/php/production/entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

COPY .docker/php/production/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/bin/ --filename=composer

RUN --mount=type=secret,id=aws,target=/root/.aws/credentials \
    aws s3 cp s3://trig-us-west-1/main/.env.production .env

COPY composer.lock composer.json ./
RUN composer install --optimize-autoloader --no-dev --no-progress --no-interaction --no-plugins --no-scripts --no-autoloader

COPY . .

RUN chown -R www-data:www-data \
    ./storage \
    ./bootstrap/cache

RUN composer dump-autoload --optimize

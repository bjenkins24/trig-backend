# syntax = docker/dockerfile:1.0-experimental

FROM php:7.4-fpm
USER root
SHELL ["/bin/bash", "-c"]
ARG BUILD_ENVIRONMENT
WORKDIR /var/www/html

#RUN apt-get update && apt-get install -y --no-install-recommends apt-utils git unzip netcat awscli
RUN apt-get update && apt-get install -y --no-install-recommends apt-utils awscli
#RUN curl -sS https://getcomposer.org/installer | \
#    php -- --install-dir=/usr/bin/ --filename=composer
#
#RUN docker-php-ext-install pcntl bcmath mysqli pdo pdo_mysql
#
#RUN yes "" | pecl install redis \
#    && rm -rf /tmp/pear \
#    && docker-php-ext-enable redis
#
#COPY --chown=www-data:www-data . .

RUN --mount=type=secret,id=aws,target=/root/.aws/credentials \
    aws s3 cp s3://trig-us-west-1/main/.env.$BUILD_ENVIRONMENT .env;

#RUN if [ "$BUILD_ENVIRONMENT" != "development" ]; then \
#    composer install --no-dev --no-progress --no-interaction --no-plugins --no-scripts; \
#    fi

COPY .docker/php/entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

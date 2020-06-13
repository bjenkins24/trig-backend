FROM php:7.4-fpm   
# ARG BUILD_ENVIRONMENT
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends apt-utils git unzip netcat
RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/bin/ --filename=composer

RUN docker-php-ext-install pcntl bcmath mysqli pdo pdo_mysql

RUN yes "" | pecl install redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis
# COPY entrypoint.sh /entrypoint.sh
# COPY --chown=www-data:www-data . .

# ENTRYPOINT ["/entrypoint.sh"]

# ENV COMPOSER_PROCESS_TIMEOUT=1200
# RUN if [ "$BUILD_ENVIRONMENT" != "development" ]; then \
#     composer install --no-dev --no-progress --no-interaction; \
#     fi

# docker build -t trig --build-arg BUILD_ENVIRONMENT=development .
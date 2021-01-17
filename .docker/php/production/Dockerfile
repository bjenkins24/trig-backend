# syntax = docker/dockerfile:1.0-experimental

FROM php:7.4-fpm
USER root
SHELL ["/bin/bash", "-c"]
ARG NEW_RELIC_AGENT_VERSION
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends apt-utils git unzip netcat awscli supervisor libpng-dev libonig-dev libjpeg-dev libfreetype6-dev libwebp-dev libxpm-dev zlib1g \
    nodejs npm gconf-service libasound2 libatk1.0-0 libc6 \
    libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgcc1 \
    libgconf-2-4 libgdk-pixbuf2.0-0 libglib2.0-0 libgtk-3-0 libnspr4 \
    libpango-1.0-0 libpangocairo-1.0-0 libstdc++6 libx11-6 libx11-xcb1 \
    libxcb1 libxcomposite1 libxcursor1 libxdamage1 libxext6 libxfixes3 \
    libxi6 libxrandr2 libxrender1 libxss1 libxtst6 ca-certificates \
    fonts-liberation libappindicator1 libnss3 lsb-release xdg-utils wget

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

COPY composer.lock composer.json nova ./
RUN composer install --no-dev --no-progress --no-interaction --no-plugins --no-scripts --no-autoloader

# Have to npm install for puppeteer
COPY package.json package-lock.json ./
RUN npm ci --only=prod

COPY . .

RUN chown -R www-data:www-data \
    ./storage \
    ./bootstrap/cache

RUN composer dump-autoload --optimize

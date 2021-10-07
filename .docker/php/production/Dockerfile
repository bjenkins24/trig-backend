# syntax = docker/dockerfile:1.0-experimental

FROM php:7.4-fpm
USER root
SHELL ["/bin/bash", "-c"]
ARG NEW_RELIC_AGENT_VERSION
WORKDIR /var/www/html

RUN apt-get update

# Needed for node 14
RUN curl -sL https://deb.nodesource.com/setup_14.x | bash -

RUN apt-get install -y --no-install-recommends apt-utils git unzip netcat awscli supervisor libpng-dev libonig-dev libjpeg-dev libfreetype6-dev libwebp-dev libxpm-dev zlib1g libgbm-dev \
    optipng pngquant jpegoptim gifsicle webp \
    nodejs gconf-service libasound2 libatk1.0-0 libc6 \
    libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgcc1 \
    libgconf-2-4 libgdk-pixbuf2.0-0 libglib2.0-0 libgtk-3-0 libnspr4 \
    libpango-1.0-0 libpangocairo-1.0-0 libstdc++6 libx11-6 libx11-xcb1 \
    libxcb1 libxcomposite1 libxcursor1 libxdamage1 libxext6 libxfixes3 \
    libxi6 libxrandr2 libxrender1 libxss1 libxtst6 ca-certificates \
    fonts-liberation libdbusmenu-gtk4 libnss3 lsb-release xdg-utils wget

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-xpm --with-webp

RUN docker-php-ext-install pcntl bcmath mysqli pdo pdo_mysql sockets gd

# Needs to be installed globally
RUN npm install -g svgo

RUN yes "" | pecl install redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

COPY .docker/php/production/entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

RUN curl -L https://download.newrelic.com/php_agent/archive/${NEW_RELIC_AGENT_VERSION}/newrelic-php5-${NEW_RELIC_AGENT_VERSION}-linux.tar.gz | tar -C /tmp -zx && \
  export NR_INSTALL_USE_CP_NOT_LN=1 && \
  export NR_INSTALL_SILENT=1 && \
  /tmp/newrelic-php5-*/newrelic-install install && \
  rm -rf /tmp/newrelic-php5-* /tmp/nrinstall* && \
  sed -i \
      -e 's/"REPLACE_WITH_REAL_KEY"/"670cb501790843d985f5ea29d6489f334d49NRAL"/' \
      -e 's/newrelic.appname = "PHP Application"/newrelic.appname = "trig-production"/' \
      -e 's/;newrelic.daemon.app_connect_timeout =.*/newrelic.daemon.app_connect_timeout=15s/' \
      -e 's/;newrelic.daemon.start_timeout =.*/newrelic.daemon.start_timeout=5s/' \
      /usr/local/etc/php/conf.d/newrelic.ini

COPY .docker/php/production/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
COPY .docker/php/production/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY .docker/php/production/php.ini /usr/local/etc/php/php.ini

RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/bin/ --filename=composer

RUN --mount=type=secret,id=aws,target=/root/.aws/credentials \
    aws s3 cp s3://trig-us-west-1/main/.env.production .env

# Ideally this should go below composer install and npm ci, but composer won't install nova correctly for some reason
# even after copying the ./nova folder. So this is a hack.
COPY . .

## Have to npm install for puppeteer
#COPY package.json package-lock.json ./
RUN npm ci --only=prod
RUN npm run build-nova:prod

#COPY composer.lock composer.json nova ./
RUN composer install --no-ansi --no-progress --no-interaction --no-plugins --no-scripts --no-autoloader

RUN chown -R www-data:www-data \
    ./storage \
    ./bootstrap/cache

RUN composer dump-autoload --optimize

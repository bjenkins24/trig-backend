# syntax = docker/dockerfile:1.0-experimental

FROM php:7.4-fpm
USER root
SHELL ["/bin/bash", "-c"]
ARG NEW_RELIC_AGENT_VERSION
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends apt-utils git unzip netcat awscli

RUN docker-php-ext-install pcntl bcmath mysqli pdo pdo_mysql

RUN yes "" | pecl install redis \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable redis

# Production below
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

RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/bin/ --filename=composer

RUN --mount=type=secret,id=aws,target=/root/.aws/credentials \
    aws s3 cp s3://trig-us-west-1/main/.env.production .env

COPY composer.lock composer.json ./
RUN composer install --no-dev --no-progress --no-interaction --no-plugins --no-scripts --no-autoloader

COPY . .

RUN chown -R www-data:www-data \
    ./storage \
    ./bootstrap/cache

RUN composer dump-autoload --optimize

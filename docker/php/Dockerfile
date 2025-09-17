FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
      libpq-dev git unzip libcurl4-openssl-dev \
  && docker-php-ext-install pdo_pgsql pgsql mbstring curl \
  && docker-php-ext-enable opcache \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY php.ini /usr/local/etc/php/php.ini

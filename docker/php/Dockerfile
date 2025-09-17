FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
      nginx-light supervisor gettext-base ca-certificates \
      libpq-dev git unzip libcurl4-openssl-dev \
  && docker-php-ext-install pdo_pgsql pgsql \
  && docker-php-ext-enable opcache \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

RUN mkdir -p /usr/local/etc/php/conf.d /etc/nginx/conf.d /run/nginx
COPY docker/nginx/default.conf.template /etc/nginx/conf.d/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["/../entrypoint.sh"]

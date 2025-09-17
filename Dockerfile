FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
      nginx supervisor libpq-dev git unzip ca-certificates curl \
  && docker-php-ext-install pdo_pgsql pgsql \
  && docker-php-ext-enable opcache \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

RUN mkdir -p /usr/local/etc/php/conf.d
RUN printf "display_errors=0\nlog_errors=1\ndate.timezone=UTC\nopcache.enable=1\nopcache.enable_cli=1\n" \
    > /usr/local/etc/php/conf.d/app.ini

RUN mkdir -p /etc/nginx/conf.d /run/nginx
COPY docker/nginx/default.conf.template /etc/nginx/conf.d/default.conf.template

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh


CMD ["/entrypoint.sh"]

# ./Dockerfile
FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*


ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf


ENV APACHE_LISTEN_PORT=8080
RUN sed -ri 's!Listen 80!Listen ${APACHE_LISTEN_PORT}!' /etc/apache2/ports.conf

WORKDIR /var/www/html
COPY . /var/www/html


RUN mkdir -p /var/data/uploads && ln -sf /var/data/uploads /var/www/html/public/uploads

EXPOSE 8080
CMD ["bash","-lc","sed -ri 's!<VirtualHost \\*:8080>!<VirtualHost *:${PORT:-8080}>!' /etc/apache2/sites-available/000-default.conf; \
                  sed -ri 's!Listen 8080!Listen ${PORT:-8080}!' /etc/apache2/ports.conf; \
                  exec apache2-foreground"]

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev sqlite3 libzip-dev \
    && docker-php-ext-install pdo pdo_sqlite zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY app/ /var/www/html/

RUN mkdir -p /data \
    && chown -R www-data:www-data /data /var/www/html

ENV DORA_DB_PATH=/data/assets.sqlite

EXPOSE 80

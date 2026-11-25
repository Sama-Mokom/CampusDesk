FROM composer:2@sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b AS composer
FROM php:8.3-apache@sha256:499556533c2a9d0614b51be13ade91120181abfd4ca5abb4063b5f6a68b8834e

ARG APP_BUILD_SHA=unknown
ARG APP_BUILD_TIME=unknown
ENV APP_BUILD_SHA=${APP_BUILD_SHA}
ENV APP_BUILD_TIME=${APP_BUILD_TIME}

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && docker-php-ext-install pdo_mysql pcntl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

COPY . .

RUN composer run-script post-autoload-dump \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini

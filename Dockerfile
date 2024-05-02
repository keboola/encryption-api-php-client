ARG PHP_VERSION=8.2

FROM php:${PHP_VERSION:-8.2}-cli

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /code

RUN apt-get update && apt-get install -y \
        git \
        unzip \
   --no-install-recommends && rm -r /var/lib/apt/lists/*

COPY ./docker/php/php.ini /usr/local/etc/php/php.ini
COPY ./docker/php/xdebug.ini /usr/local/etc/php/conf.d/

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

RUN if [[ "$PHP_VERSION" != 7.4* ]]; then \
    pecl install xdebug && \
    docker-php-ext-enable xdebug; \
    fi

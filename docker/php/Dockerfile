# The host has no PHP, so every PHP command runs here. PHP_VERSION picks the CI leg (8.2 or 8.4).
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions bcmath gd intl pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN echo "memory_limit=1G" > /usr/local/etc/php/conf.d/memory.ini

WORKDIR /app

FROM ghcr.io/eventpoints/php:sha-ea6c165 AS composer

ENV APP_ENV="prod" \
    APP_DEBUG=0 \
    PHP_OPCACHE_PRELOAD="/app/config/preload.php" \
    PHP_EXPOSE_PHP="off" \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

RUN rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN mkdir -p var/cache var/log

COPY composer.json composer.lock symfony.lock ./

RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

FROM node:22 as js-builder

WORKDIR /build

# We need /vendor here
COPY --from=composer /app .

# Production yarn build
COPY ./assets ./assets

FROM composer as php

COPY --from=js-builder /build .
COPY . .

RUN npm install -g sass
# Need to run again to trigger scripts with application code present
RUN composer install --no-dev --no-interaction --classmap-authoritative
RUN composer symfony:dump-env prod
RUN chmod -R 777 var

FROM ghcr.io/eventpoints/caddy:sha-fc43d4e AS caddy

COPY --from=php /app/public public/
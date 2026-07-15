# --- Vite/Tailwind asset build ------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY assets ./assets
COPY templates ./templates
COPY src ./src
RUN npm run build

# --- Composer / PHP -----------------------------------------------------------
FROM ghcr.io/eventpoints/php:main AS composer

ENV APP_ENV="prod" \
    APP_DEBUG=0 \
    PHP_OPCACHE_PRELOAD="/app/config/preload.php" \
    PHP_EXPOSE_PHP="off" \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

RUN rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN mkdir -p var/cache var/log

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

FROM composer AS php

# now copy the full app
COPY . .

# bring in the compiled Vite assets (public/build) from the node stage
COPY --from=assets /app/public/build ./public/build

# run composer again now that the app code exists (if you rely on flex auto-scripts)
RUN composer install --no-dev --no-interaction --classmap-authoritative

RUN composer symfony:dump-env prod
RUN chmod -R 777 var

FROM ghcr.io/eventpoints/caddy:main AS caddy
COPY --from=php /app/public public/

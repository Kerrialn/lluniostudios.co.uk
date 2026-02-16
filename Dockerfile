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

# ------------------ build front-end assets ------------------
FROM node:22 AS js-builder
WORKDIR /app

# copy only what npm needs first for caching
COPY package.json package-lock.json ./
RUN npm ci

# now copy front-end sources + configs
COPY assets ./assets
COPY public ./public
# COPY webpack.config.js postcss.config.js tailwind.config.js .  # if you have these

RUN npm run build
# (this should compile scss -> css as part of your build)

# ------------------ final php image ------------------
FROM composer AS php
# bring back the whole app + vendor from composer stage
COPY . .

# copy built assets into public (common output for Encore/Vite/etc)
COPY --from=js-builder /app/public /app/public

RUN composer install --no-dev --no-interaction --classmap-authoritative
RUN composer symfony:dump-env prod
RUN chmod -R 777 var

FROM ghcr.io/eventpoints/caddy:main AS caddy
COPY --from=php /app/public public/

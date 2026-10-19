#syntax=docker/dockerfile:1

# FrankenPHP: PHP and Caddy in one binary, one container. Replaces the separate
# php-fpm and Caddy images (ghcr.io/eventpoints/php and caddy). Pinned to a
# minor so a rebuild does not silently change PHP or Caddy versions.
FROM dunglas/frankenphp:1.13-php8.4-bookworm AS frankenphp_base

WORKDIR /app

VOLUME /app/var/

RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
	file \
	git \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		gd \
		intl \
		opcache \
		pdo_pgsql \
		zip \
	;

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# ---------------------------------------------------------------------------
# Development: the repo is bind-mounted over /app, so nothing is copied in.
# ---------------------------------------------------------------------------
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
	install-php-extensions \
		xdebug \
	;

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

# ---------------------------------------------------------------------------
# Production
# ---------------------------------------------------------------------------
FROM frankenphp_base AS composer_deps

ENV APP_ENV=prod
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# The PHP image has no Node; Vite runs in its own stage and only its output is kept.
FROM node:22-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
# Tailwind scans templates/ and src/ for class names.
COPY assets ./assets
COPY templates ./templates
COPY src ./src
COPY vite.config.js ./
RUN npm run build

FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

COPY --link --from=composer_deps /app/vendor ./vendor
COPY --link . ./
COPY --link --from=assets /app/public/build ./public/build
RUN rm -Rf frankenphp/

# Vich writes uploaded images to public/uploads (the shared `uploads` volume in
# the production compose file).
RUN set -eux; \
	mkdir -p var/cache var/log public/uploads; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	php bin/console cache:warmup; \
	chmod +x bin/console; sync;

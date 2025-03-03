FROM composer:2 AS builder

WORKDIR /build/

COPY composer.* .

RUN composer validate --strict

RUN composer install --no-interaction --no-progress --ignore-platform-reqs --prefer-dist

FROM php:8.1-cli-alpine AS base_php

RUN apk update && \
    apk add --no-cache --virtual .dev-exts \
        $PHPIZE_DEPS \
        linux-headers \
        && \
    pecl install xdebug-3.4.1 && \
    docker-php-ext-enable xdebug && \
    apk del --no-cache .dev-exts && \
    cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

FROM base_php AS runner

ARG COMPOSER_ROOT_VERSION=1.0.0
ENV COMPOSER_ROOT_VERSION=$COMPOSER_ROOT_VERSION
ENV XDEBUG_MODE=coverage

ARG UID=1000
ARG GID=1000
ENV ENV="/etc/profile"

RUN addgroup -g $GID lazy-json && \
    adduser -u $UID -G lazy-json -D lazy-json && \
    echo -e "#!/bin/sh\nexport PATH=\"$PATH:/usr/share/lazy-json/vendor/bin\"" >> /etc/profile.d/profile.sh

USER lazy-json

WORKDIR /usr/share/lazy-json/

COPY --from=builder --chown=lazy-json:lazy-json /usr/bin/composer /usr/bin/composer
COPY --from=builder --chown=lazy-json:lazy-json /build/vendor ./vendor

COPY --chown=lazy-json:lazy-json . .

RUN composer check-platform-reqs

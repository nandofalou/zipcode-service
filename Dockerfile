FROM composer:2 AS builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY src/ src/
COPY config/ config/
COPY public/ public/
COPY database/ database/

FROM php:8.4-fpm-alpine

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

WORKDIR /app

COPY --from=builder /app/vendor /app/vendor
COPY --from=builder /app/src /app/src
COPY --from=builder /app/config /app/config
COPY --from=builder /app/public /app/public
COPY --from=builder /app/database /app/database

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app \
    && mkdir -p /app/data \
    && chown -R app:app /app

USER app

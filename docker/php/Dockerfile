# syntax=docker/dockerfile:1

# ---------------------------------------------------------------- assets
#
# The frontend is built in its own stage so Node never reaches the final image.
# Shipping a Node toolchain to production would roughly double the image for
# something only the build needs.
FROM node:24-alpine AS assets

WORKDIR /app

# Copied first so a change to application code does not invalidate the npm layer.
COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js jsconfig.json ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ---------------------------------------------------------------- vendor
#
# Composer likewise stays out of the runtime image.
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts because artisan is not present yet at this point, and the package
# discovery those scripts perform runs again in the final stage.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev

# ---------------------------------------------------------------- runtime
FROM php:8.3-fpm-alpine AS app

WORKDIR /var/www/html

# icu-dev is needed to build intl; the -dev packages are removed afterwards so
# they do not sit in the final layer.
RUN apk add --no-cache \
        icu-libs libzip libpng libjpeg-turbo freetype oniguruma \
    && apk add --no-cache --virtual .build-deps \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# www-data owns only what the application actually writes to; the rest of the tree
# stays read-only to the pool.
RUN chown -R www-data:www-data storage bootstrap/cache

# No `USER www-data` here on purpose. The php-fpm master starts as root and drops
# its workers to www-data through the default pool, which is the arrangement the
# base image is built and tested around. Forcing the master to a non-root user is a
# common way to get an unreadable error log and a container that exits without
# saying why — and it is not a change worth making untested.

EXPOSE 9000

CMD ["php-fpm"]

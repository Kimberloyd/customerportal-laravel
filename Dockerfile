# Multi-stage build: compile frontend assets, then build the PHP-FPM
# runtime image. Mirrors the sibling Flask app's Dockerfile conventions
# (non-root user, minimal runtime layer) -- see /workspace/customerportal/Dockerfile.
#
# NOTE: unlike the Flask Dockerfile, these base images are pinned by
# tag only, not by digest. This sandbox has no registry access to look
# up the current digest for either image, so a plausible-looking digest
# would just be a guess dressed up as a fact -- pin these by digest
# deliberately once a real build has been run and the digest is known,
# the same way the Flask image documents doing.
FROM node:20-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        zip \
        gd \
        opcache \
    && apk del .build-deps

RUN addgroup -S app && adduser -S -G app -h /app app

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader --no-progress

COPY --chown=app:app . .
COPY --from=assets --chown=app:app /app/public/build ./public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/app/private/purchase_order_attachments \
        bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R u+rwX storage bootstrap/cache

USER app

EXPOSE 9000

CMD ["php-fpm"]

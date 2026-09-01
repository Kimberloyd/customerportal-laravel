# Multi-stage build: compile frontend assets, then build the PHP-FPM
# runtime image. Mirrors the sibling Flask app's Dockerfile conventions
# (non-root user, minimal runtime layer) -- see /workspace/customerportal/Dockerfile.
#
# Keep the readable tag for maintenance while the verified digest makes
# the build immutable. Refresh both together during a deliberate upgrade.
FROM node:26-slim@sha256:c0753125a3789977aefe869cbebccf70e3cfd7ea84ca48547458f02e4f1d7146 AS assets
WORKDIR /app
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

FROM php:8.4-fpm-alpine@sha256:6cb5e4ffa03a7c1b01bb5b120ab3684ef76b75aa5ca417e343936db3f71f419f AS runtime

RUN apk add --no-cache \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        su-exec \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
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
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

RUN apk add --no-cache --virtual .pcntl-build-deps $PHPIZE_DEPS \
    && docker-php-ext-install pcntl \
    && apk del .pcntl-build-deps

RUN addgroup -S app && adduser -S -G app -h /app app

# Base image's default pool runs workers as www-data, which doesn't
# exist in this image -- point it at the `app` user/group created
# above. Master process itself stays root (see entrypoint.sh) so it
# can still open error_log (/proc/self/fd/2); only workers, which run
# the actual PHP/Laravel request code, drop to `app`.
RUN sed -i \
        -e 's/^user = .*/user = app/' \
        -e 's/^group = .*/group = app/' \
        /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app

COPY --from=composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-plugins --no-interaction --optimize-autoloader --no-progress

COPY --chown=app:app . .
COPY --from=assets --chown=app:app /app/public/build ./public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/app/private/purchase_order_attachments \
        bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R u+rwX storage bootstrap/cache

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Deliberately stay root here, not `USER app` -- the tmpfs mounts for
# storage/framework/{cache,sessions,views} and bootstrap/cache
# (docker-compose.yml) are recreated fresh, owned by root, on every
# container start, so something has to re-chown them before the app
# user can write to them. entrypoint.sh does that chown, then execs
# php-fpm still as root -- its master process needs root to open
# error_log (/proc/self/fd/2); only its worker processes (which run
# the actual PHP/Laravel code, per www.conf's user/group above) drop
# to the unprivileged `app` user.

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

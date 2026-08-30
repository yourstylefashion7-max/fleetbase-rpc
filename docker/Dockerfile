# syntax = docker/dockerfile:1.2
# Base stage
FROM dunglas/frankenphp:1.12.4-php8.2-bookworm@sha256:485873a0b6b802b6ebaf285c0021ee2fb8e80a162a08d2d4c5311bb5cc998da3 AS base

ARG SOURCE_REVISION=uncommitted
ARG SOURCE_URL=https://github.com/yourstylefashion7-max/fleetbase-rpc
ARG BUILD_CREATED=unknown
ARG PATCHSET_SHA256=unknown

LABEL org.opencontainers.image.source="${SOURCE_URL}" \
      org.opencontainers.image.revision="${SOURCE_REVISION}" \
      org.opencontainers.image.created="${BUILD_CREATED}" \
      org.opencontainers.image.version="0.7.55-tenant" \
      org.opencontainers.image.description="Fleetbase v0.7.55 with reviewed multi-organization isolation overlays" \
      com.rapidpeach.fleetbase.patchset-sha256="${PATCHSET_SHA256}"

# Install packages
RUN apt-get update && apt-get install -y git bind9-utils mycli nodejs npm nano uuid-runtime \
  && mkdir -p /root/.ssh \
  && ssh-keyscan github.com >> /root/.ssh/known_hosts

# Install PHP Extensions
RUN install-php-extensions \
  pdo_mysql \
  gd \
  bcmath \
  redis \
  intl \
  zip \
  gmp \
  apcu \
  opcache \
  memcached \
  imagick \
  # geos \
  sockets \
  pcntl \
  xdebug \
  @composer-2.10.3

# Install build dependencies for GEOS
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       pkg-config \
       libgeos-dev \
       libgeos++-dev \
       autoconf \
       build-essential \
       unzip \
    && rm -rf /var/lib/apt/lists/*

# Download, extract, compile, and enable the php-geos extension
RUN set -eux; \
    curl -fsSL -o php-geos.zip \
      https://github.com/libgeos/php-geos/archive/dfe1ab17b0f155cc315bc13c75689371676e02e1.zip \
    && echo "b04ad8d16e17e4d04ce7f03ee99a8849281ab367913f515bd75454bed3d46984  php-geos.zip" | sha256sum -c - \
    && unzip php-geos.zip \
    && rm php-geos.zip \
    && cd php-geos-* \
    && ./autogen.sh \
    && ./configure \
    && make -j"$(nproc)" install \
    && docker-php-ext-enable geos \
    && cd .. \
    && rm -rf php-geos-*

# Update PHP configurations
RUN sed -e 's/^expose_php.*/expose_php = Off/' "$PHP_INI_DIR/php.ini-production" > "$PHP_INI_DIR/php.ini" \
  && sed -i -e 's/^upload_max_filesize.*/upload_max_filesize = 600M/' -e 's/^post_max_size.*/post_max_size = 0/' \
  -e 's/^memory_limit.*/memory_limit = 600M/' "$PHP_INI_DIR/php.ini"

# Install only the Node utilities used by API package workflows. The console
# has its own image and local Ember CLI dependency.
RUN npm install -g \
  chokidar@3.6.0 \
  pnpm@9.15.9 \
  npm-cli-login@1.0.0

# Install ssm-parent
COPY --from=ghcr.io/springload/ssm-parent:1.8@sha256:c3bfb721bf050ed1ef58d175e7b9fbe9f9cd4c2810db66636a13b5519168928f /usr/bin/ssm-parent /sbin/ssm-parent

# Create the pnpm directory and set the PNPM_HOME environment variable
RUN mkdir -p ~/.pnpm
ENV PNPM_HOME=/root/.pnpm

# Add the pnpm global bin to the PATH
ENV PATH=/root/.pnpm/bin:$PATH

# Set some build ENV variables
ENV LOG_CHANNEL=stdout
ENV CACHE_DRIVER=null
ENV BROADCAST_DRIVER=socketcluster
ENV QUEUE_CONNECTION=redis
ENV CADDYFILE_PATH=/fleetbase/Caddyfile
ENV CONSOLE_PATH=/fleetbase/console
ENV OCTANE_SERVER=frankenphp
ENV FLEETBASE_VERSION=0.7.55

# Set environment
ARG ENVIRONMENT=production
ENV APP_ENV=$ENVIRONMENT

# Copy Caddyfile
COPY --chown=www-data:www-data ./Caddyfile $CADDYFILE_PATH

# Create /fleetbase directory and set correct permissions
RUN mkdir -p /fleetbase/api && mkdir -p /fleetbase/console && chown -R www-data:www-data /fleetbase

# Generate and store a UUID in .fleetbase-id
RUN uuidgen > /fleetbase/api/.fleetbase-id

# Export the same ID as an environment variable for use in the container
RUN export FLEETBASE_INSTANCE_ID=$(cat /fleetbase/api/.fleetbase-id) && \
    echo "FLEETBASE_INSTANCE_ID=$FLEETBASE_INSTANCE_ID" >> /etc/environment

# Set working directory
WORKDIR /fleetbase/api

# Prepare composer cache directory
RUN mkdir -p /var/www/.cache/composer && chown -R www-data:www-data /var/www/.cache/composer

# Optimize Composer Dependency Installation
COPY --chown=www-data:www-data ./api/composer.json ./api/composer.lock /fleetbase/api/

# Pre-install Composer dependencies
RUN su www-data -s /bin/sh -c "composer install --no-scripts --optimize-autoloader --no-dev --no-cache"

# Backport Laravel's upstream CRLF email hardening to the unsupported 10.x
# line used by Fleetbase v0.7.55. The patch command fails closed if the locked
# framework source ever drifts from the reviewed base.
COPY ./docker/security-patches /tmp/fleetbase-security-patches
RUN set -eux; \
    patch --dry-run -p1 -d /fleetbase/api/vendor/laravel/framework -i /tmp/fleetbase-security-patches/laravel-framework-10-crlf-email.patch; \
    patch -p1 -d /fleetbase/api/vendor/laravel/framework -i /tmp/fleetbase-security-patches/laravel-framework-10-crlf-email.patch; \
    php /tmp/fleetbase-security-patches/verify-laravel-framework-10-crlf-email.php; \
    rm -rf /tmp/fleetbase-security-patches; \
    su www-data -s /bin/sh -c "composer audit --locked --no-dev --abandoned=report"

# Overlay reviewed tenant-isolation changes onto the exact Composer package
# versions locked by Fleetbase v0.7.55. Keeping these overlays in this
# repository makes the production image reproducible without unpublished
# package forks.
COPY --chown=www-data:www-data ./docker/tenant-overlays /tmp/fleetbase-tenant-overlays
RUN set -eux; \
    for package in core-api fleetops-api ledger-api registry-bridge storefront-api; do \
        test -d "/fleetbase/api/vendor/fleetbase/$package"; \
        cp -a "/tmp/fleetbase-tenant-overlays/$package/." "/fleetbase/api/vendor/fleetbase/$package/"; \
    done; \
    rm -rf /tmp/fleetbase-tenant-overlays

# Setup application
COPY --chown=www-data:www-data ./api /fleetbase/api

# Dump autoload
RUN su www-data -s /bin/sh -c "composer dumpautoload"

# Setup composer root directory
RUN mkdir -p /root/.composer
RUN mkdir -p /fleetbase/api/.composer && chown www-data:www-data /fleetbase/api/.composer

# Setup logging
RUN mkdir -p /fleetbase/api/storage/logs/ && touch /fleetbase/api/storage/logs/laravel-$(date +'%Y-%m-%d').log
RUN chown -R www-data:www-data /fleetbase/api/storage
RUN chmod -R 755 /fleetbase/api/storage

# Set permissions for deploy script
RUN chmod +x /fleetbase/api/deploy.sh

# Install go-crond
RUN set -eux; \
    curl -fsSL -o /usr/local/bin/go-crond https://github.com/webdevops/go-crond/releases/download/23.12.0/go-crond.linux.amd64; \
    echo "15f3f4cd76d6fbe397eec0ddd3f3f8589d7a98da57e6f5ff54852dec6a49badd  /usr/local/bin/go-crond" | sha256sum -c -; \
    chmod +x /usr/local/bin/go-crond
COPY docker/crontab ./crontab
RUN chmod 0600 ./crontab

# Scheduler dev stage
FROM base AS scheduler-dev
ENTRYPOINT []
CMD ["go-crond", "--verbose", "root:./crontab"]

# Scheduler stage
FROM base AS scheduler
ENTRYPOINT ["/sbin/ssm-parent", "-c", ".ssm-parent.yaml", "run", "--"]
CMD ["go-crond", "--verbose", "root:./crontab"]

# Events stage
FROM base AS events
ENTRYPOINT ["/sbin/ssm-parent", "-c", ".ssm-parent.yaml", "run", "--", "docker-php-entrypoint"]
CMD ["php", "artisan", "queue:work"]

# Events stage
FROM base AS events-dev
ENTRYPOINT []
CMD ["php", "artisan", "queue:work"]

# Application dev stage
FROM base AS app-dev
ENTRYPOINT ["docker-php-entrypoint"]
CMD ["sh", "-c", "php artisan octane:frankenphp --max-requests=1000 --port=8000 --host=0.0.0.0 --watch"]

# Application release stage
FROM base AS app-release
ENTRYPOINT ["docker-php-entrypoint"]
CMD ["sh", "-c", "php artisan octane:frankenphp --max-requests=1000 --port=8000 --host=0.0.0.0"] 

# Application stage
FROM base AS app
ENTRYPOINT ["/sbin/ssm-parent", "-c", ".ssm-parent.yaml", "run", "--", "docker-php-entrypoint"]
CMD ["sh", "-c", "php artisan octane:frankenphp --max-requests=1000 --port=8000 --host=0.0.0.0"]

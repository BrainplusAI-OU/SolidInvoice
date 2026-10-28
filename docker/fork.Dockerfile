# syntax=docker/dockerfile:1
#
# A plain FrankenPHP image of this checkout, for the BrainplusAI fork's test deployments. The
# official image (docker/package.Dockerfile) wraps the static binary built by the release
# workflows instead; this one builds in a few minutes from source.

FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions bcmath gd intl opcache pdo_mysql pdo_pgsql redis soap sodium xsl zip

ENV SOLIDINVOICE_ENV=prod \
    SOLIDINVOICE_DEBUG=0 \
    SOLIDINVOICE_CONFIG_DIR=/etc/solidinvoice \
    SOLIDINVOICE_DOCKER=true \
    APP_PATH=/app \
    SERVER_NAME=:8765

WORKDIR /app

FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

FROM oven/bun:1.3.14 AS assets

WORKDIR /app
COPY package.json bun.lock tsconfig.json webpack.config.js ./
COPY --from=vendor /app/vendor/solidworx/platform vendor/solidworx/platform
RUN bun install --frozen-lockfile
COPY assets assets
COPY src src
COPY templates templates
RUN bun run build

FROM base

COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor vendor
COPY . .
COPY --from=assets /app/public/build public/build
COPY Caddyfile /etc/frankenphp/Caddyfile
COPY docker/fork-entrypoint.sh /usr/local/bin/fork-entrypoint

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p var /etc/solidinvoice \
    && chmod +x /usr/local/bin/fork-entrypoint \
    && php bin/console assets:install public --no-interaction

EXPOSE 8765
VOLUME ["/etc/solidinvoice"]

ENTRYPOINT ["fork-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

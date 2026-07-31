# syntax=docker/dockerfile:1
#
# Self-contained demo image for the Emagma Sylius Order Withdrawal plugin.
#
#   FrankenPHP (serves the Sylius test application that mounts this plugin)
#   + Mailpit   (captures every outgoing email — web UI on :8025)
#   + SQLite    (file-backed DB baked into the image — no external service)
#   + fixtures  (the default Sylius sample data, loaded at build time)
#
# Dependencies are resolved at build time for the Symfony line given by SYMFONY_VERSION
# (Flex reads it from extra.symfony.require), so the same Dockerfile produces one image per
# supported Symfony line — see .gitlab-ci.yml for the 6.4 / 7.1 matrix (Sylius 2.0 core itself
# requires ^6.4.1 || ^7.1). Build context is the repo root; vendor/ is resolved fresh (it is
# excluded via .dockerignore).
#
# Image name = project registry; tag = "<branch>-sf<major>", e.g. 2.0-sf6 / 2.0-sf7:
#   IMAGE=registry.gitlab.com/emagma/sylius-plugins/order-withdrawal-plugin
#   docker build -f docker/demo/Dockerfile \
#     --build-arg SYMFONY_VERSION="6.4.*" -t "$IMAGE:2.0-sf6" .
#
# Run (try the demo):
#   docker run --rm -p 8080:80 -p 8025:8025 "$IMAGE:2.0-sf6"
#   → shop at http://localhost:8080  ·  Mailpit at http://localhost:8025
#
# Run (CI, e.g. the test suite against the provisioned DB):
#   docker run --rm "$IMAGE:2.0-sf6" vendor/bin/phpunit
ARG PHP_VERSION=8.3
ARG FRANKENPHP_VERSION=1.12.4
ARG COMPOSER_VERSION=2
ARG NODEJS_VERSION=20
ARG MAILPIT_VERSION=v1.30.1
ARG PHP_EXTENSION_INSTALLER_VERSION=2

FROM composer/composer:${COMPOSER_VERSION} AS composer
FROM mlocati/php-extension-installer:${PHP_EXTENSION_INSTALLER_VERSION} AS php_extension_installer
FROM node:${NODEJS_VERSION}-slim AS node
FROM axllent/mailpit:${MAILPIT_VERSION} AS mailpit

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}

# Which Symfony line to resolve. The dev-deps already allow ^6.4 || ^7.1; Flex pins the rest.
ARG SYMFONY_VERSION=6.4.*
ENV SYMFONY_VERSION=${SYMFONY_VERSION}

# SQLite driver + the extensions Sylius needs.
COPY --from=php_extension_installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl pdo_sqlite gd zip bcmath exif opcache

# Composer, Node + classic yarn (assets are built here), Mailpit, and git/unzip for Composer.
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node /usr/local/bin /usr/local/bin
COPY --from=node /opt /opt
COPY --from=mailpit /mailpit /usr/local/bin/mailpit
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/demo/php.ini ${PHP_INI_DIR}/conf.d/zz-demo.ini

# Served kernel configuration: the test-application kernel reads these to mount THIS plugin
# (paths resolve against the plugin root, i.e. /app). Mirrors the dev stack's env.
ENV APP_ENV=dev \
    APP_DEBUG=0 \
    APP_SECRET=feature_test_secret \
    DATABASE_URL="sqlite:////app/var/withdrawal.sqlite" \
    MAILER_DSN="smtp://127.0.0.1:1025" \
    SYLIUS_FIXTURES_HOSTNAME=localhost \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    SYLIUS_TEST_APP_BUNDLES_PATH=tests/TestApplication/bundles.php \
    SYLIUS_TEST_APP_CONFIGS_TO_IMPORT=@EmagmaSyliusOrderWithdrawalPlugin/tests/TestApplication/config/config.yaml \
    SYLIUS_TEST_APP_ROUTES_TO_IMPORT=@EmagmaSyliusOrderWithdrawalPlugin/tests/TestApplication/config/routes.yaml

WORKDIR /app
COPY . /app

# Resolve deps for the requested Symfony line → build front-end assets → provision the baked
# SQLite database. The schema is created from ORM metadata (doctrine:schema:create) because
# the core + plugin migrations are MySQL-specific. node_modules + caches are dropped after.
RUN set -eux; \
    rm -rf vendor var/cache/* var/log/*; \
    composer config extra.symfony.require "${SYMFONY_VERSION}"; \
    composer update --no-interaction --no-progress --prefer-dist --no-scripts --no-security-blocking; \
    APP=vendor/sylius/test-application; \
    yarn --cwd "$APP" install --frozen-lockfile; \
    yarn --cwd "$APP" encore production; \
    vendor/bin/console cache:clear --no-interaction; \
    vendor/bin/console assets:install "$APP/public" --no-interaction; \
    vendor/bin/console doctrine:database:create --no-interaction; \
    vendor/bin/console doctrine:schema:create --no-interaction; \
    vendor/bin/console sylius:fixtures:load default --no-interaction; \
    composer clear-cache; \
    rm -rf "$APP/node_modules" /root/.cache/yarn

COPY docker/demo/Caddyfile /etc/caddy/Caddyfile
COPY docker/demo/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80 8025

ENTRYPOINT ["entrypoint"]

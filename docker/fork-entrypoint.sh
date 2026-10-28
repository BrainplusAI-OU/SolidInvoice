#!/bin/sh
set -e

cd /app

# Same order as the static binary's startup hook (frankenphp/app.go): bring the schema up to
# date before anything is served. Skipped until the installer has written its configuration.
if [ -n "$(ls -A "${SOLIDINVOICE_CONFIG_DIR}" 2>/dev/null)" ]; then
    php bin/console cache:clear --no-warmup
    if ! php bin/console doctrine:migrations:up-to-date --no-interaction > /dev/null 2>&1; then
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || echo "Migrations failed; starting anyway" >&2
    fi
    php bin/console cache:warmup
fi

exec "$@"

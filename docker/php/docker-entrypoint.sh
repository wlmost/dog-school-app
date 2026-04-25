#!/bin/sh
set -e

# Ensure composer dependencies (incl. dev) are installed when vendor is missing or incomplete
if [ ! -f /var/www/html/vendor/autoload.php ] || [ ! -d /var/www/html/vendor/fakerphp ]; then
    echo "[entrypoint] Installing Composer dependencies (including dev)..."
    composer install --no-interaction --prefer-dist --optimize-autoloader \
        --working-dir=/var/www/html
    echo "[entrypoint] Composer install complete."
fi

# Execute the default PHP-FPM command
exec "$@"

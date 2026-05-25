#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

if [ "${WAIT_FOR_DB:-false}" = "true" ] && [ "${DB_CONNECTION:-}" = "mysql" ]; then
  echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
  until php -r '
    $host = getenv("DB_HOST") ?: "mysql";
    $port = getenv("DB_PORT") ?: "3306";
    $db = getenv("DB_DATABASE") ?: "vortexops";
    $user = getenv("DB_USERNAME") ?: "vortexops";
    $pass = getenv("DB_PASSWORD") ?: "";
    try {
        new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
  '; do
    sleep 2
  done
fi

if [ -f artisan ]; then
  php artisan storage:link || true

  if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
  fi
fi

exec "$@"

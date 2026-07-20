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
    # A single failed attempt here (e.g. a transient connection error, or a
    # hosting-side per-hour connection quota) used to be fatal: `set -e` would
    # exit the entrypoint, `restart: unless-stopped` would immediately restart
    # the container, and the very next attempt would hit the same DB call
    # right away — a tight crash loop that burns through an hourly connection
    # quota far faster than the outage that started it. Retry with backoff
    # instead of failing on the first attempt.
    attempt=1
    max_attempts=5
    delay=3
    until php artisan migrate --force; do
      if [ "$attempt" -ge "$max_attempts" ]; then
        echo "migrate failed after ${max_attempts} attempts — giving up."
        exit 1
      fi
      echo "migrate attempt ${attempt} failed — retrying in ${delay}s..."
      sleep "$delay"
      attempt=$((attempt + 1))
      delay=$((delay * 2))
    done
  fi
fi

exec "$@"

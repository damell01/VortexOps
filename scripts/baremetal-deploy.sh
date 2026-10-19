#!/usr/bin/env bash

set -Eeuo pipefail

BRANCH="${1:-${DEPLOY_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}}"
ENVIRONMENT="${DEPLOY_ENVIRONMENT:-$([ "$BRANCH" = "Production" ] && echo production || echo development)}"
APP_DIR="$(pwd)"

if [ ! -f artisan ] || [ ! -f .env ]; then
  echo "This must run from a configured VortexOps checkout with .env present."
  exit 1
fi

if [ "$BRANCH" != "Dev" ] && [ "$BRANCH" != "Production" ]; then
  echo "Refusing to deploy unsupported branch: $BRANCH"
  exit 1
fi

maintenance_enabled=0
restore_app() {
  if [ "$maintenance_enabled" -eq 1 ]; then
    php artisan up >/dev/null 2>&1 || true
  fi
}
trap restore_app EXIT

echo "Deploying VortexOps $BRANCH ($ENVIRONMENT) from $APP_DIR"
echo "Commit: $(git rev-parse --short HEAD)"

if [ "$BRANCH" = "Production" ]; then
  php artisan down --retry=60 --secret="vortexops-deploy" || true
  maintenance_enabled=1
fi

php artisan optimize:clear

if [ "$BRANCH" = "Production" ]; then
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
else
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Cache on Redis. Every page reads settings, permissions and modules through
# the cache, and on the database store each read is a MySQL query. Once a Redis
# server answers here, move the cache to it, but only after proving it can
# write, read and lock; otherwise put .env back. Sessions and queues stay where
# they are, so nobody is signed out. Opt out with VORTEX_REDIS_CACHE=off.
env_value() { { grep -E "^$1=" .env || true; } | tail -n1 | cut -d= -f2- | tr -d "\"'"; }
set_env() {
  if grep -qE "^$1=" .env; then sed -i "s#^$1=.*#$1=$2#" .env; else printf '\n%s=%s\n' "$1" "$2" >> .env; fi
}
CACHE_NOW="$(env_value CACHE_STORE)"
if [ "$(env_value VORTEX_REDIS_CACHE)" != "off" ] && [ "${CACHE_NOW:-database}" = "database" ]; then
  REDIS_ADDR_HOST="$(env_value REDIS_HOST)"; REDIS_ADDR_HOST="${REDIS_ADDR_HOST:-127.0.0.1}"
  REDIS_ADDR_PORT="$(env_value REDIS_PORT)"; REDIS_ADDR_PORT="${REDIS_ADDR_PORT:-6379}"
  if timeout 3 bash -c "exec 3<>/dev/tcp/$REDIS_ADDR_HOST/$REDIS_ADDR_PORT" 2>/dev/null; then
    cp .env .env.before-redis
    if php -m 2>/dev/null | grep -qi '^redis$'; then REDIS_CLIENT_PICK=phpredis; else REDIS_CLIENT_PICK=predis; fi
    set_env CACHE_STORE redis
    set_env REDIS_CLIENT "$REDIS_CLIENT_PICK"
    # Production and Dev share this server's Redis; their keys must not meet.
    [ -n "$(env_value CACHE_PREFIX)" ] || set_env CACHE_PREFIX "vortexops-$ENVIRONMENT-cache-"
    php artisan config:clear >/dev/null
    if php artisan cache:probe redis --locks-free; then
      echo "Cache moved to Redis ($REDIS_CLIENT_PICK); restarting workers so they share it"
      rm -f .env.before-redis
      WORKER_CHANGED=1
    else
      echo "Redis did not pass the check (or a lock is held); cache stays on MySQL for now"
      mv .env.before-redis .env
      php artisan config:clear >/dev/null
    fi
  else
    echo "No Redis server at $REDIS_ADDR_HOST:$REDIS_ADDR_PORT; cache stays on MySQL."
    echo "To speed pages up: sudo apt install redis-server && sudo systemctl enable --now redis-server; the next deploy switches over."
  fi
fi

npm ci --no-audit --no-fund
npm run build

# Runtime only needs public/build. Removing node_modules keeps the two
# bare-metal checkouts from wasting several GB on duplicate build dependencies.
rm -rf node_modules

php artisan migrate --force
# Idempotent: keep the same encrypted Web Push keys across deployments.
if [ -f app/Console/Commands/SetupWebPush.php ]; then
  php artisan push:setup
fi

php artisan storage:link >/dev/null 2>&1 || true
php artisan filament:clear-cached-components || true
php artisan optimize
php artisan filament:optimize

# queue:restart uses each environment's configured cache namespace, so Dev and
# Production workers can restart independently when their .env files use
# distinct APP_NAME/CACHE_PREFIX values.
php artisan queue:restart

# Reload PHP-FPM/Apache OPcache when the deploy user has passwordless sudo.
FPM_UNIT="$(systemctl list-units --type=service --all --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}' | head -n1 || true)"
if [ -n "$FPM_UNIT" ]; then
  sudo -n systemctl reload "$FPM_UNIT" 2>/dev/null || true
fi
sudo -n systemctl reload apache2 2>/dev/null || true

# Restart only this environment's systemd workers if those units exist. Dev
# uses vortexops-dev-*; Production keeps the existing vortexops-* names.
if [ "$BRANCH" = "Production" ]; then
  UNIT_PREFIX="vortexops"
else
  UNIT_PREFIX="vortexops-dev"
fi

for unit in "$UNIT_PREFIX-worker" "$UNIT_PREFIX-ai-worker" "$UNIT_PREFIX-scheduler"; do
  if systemctl list-unit-files "${unit}.service" --no-legend 2>/dev/null | grep -q "${unit}.service"; then
    sudo -n systemctl restart "$unit" 2>/dev/null || true
  fi
done

php artisan health:check >/dev/null

if [ "$maintenance_enabled" -eq 1 ]; then
  php artisan up
  maintenance_enabled=0
fi

echo "Bare-metal deploy complete: $BRANCH @ $(git rev-parse --short HEAD)"

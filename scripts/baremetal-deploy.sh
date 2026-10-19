#!/usr/bin/env bash

set -Eeuo pipefail

BRANCH="${1:-${DEPLOY_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}}"
ENVIRONMENT="${DEPLOY_ENVIRONMENT:-$([ "$BRANCH" = "Production" ] && echo production || echo development)}"
APP_DIR="$(pwd)"
OLD_SHA="${DEPLOY_OLD_SHA:-}"
NEW_SHA="$(git rev-parse HEAD)"

if [ ! -f artisan ] || [ ! -f .env ]; then
  echo "This must run from a configured VortexOps checkout with .env present."
  exit 1
fi

if [ "$BRANCH" != "Dev" ] && [ "$BRANCH" != "Production" ]; then
  echo "Refusing to deploy unsupported branch: $BRANCH"
  exit 1
fi

# If the previous revision is unavailable (first deploy/manual invocation), use
# the full safe path. Otherwise classify the actual changed files.
FULL_DEPLOY=0
CHANGED_FILES=""
if [ -z "$OLD_SHA" ] || ! git cat-file -e "$OLD_SHA^{commit}" 2>/dev/null; then
  FULL_DEPLOY=1
else
  CHANGED_FILES="$(git diff --name-only "$OLD_SHA" "$NEW_SHA")"
fi

changed() {
  if [ "$FULL_DEPLOY" -eq 1 ]; then
    return 0
  fi

  local pattern="$1"
  printf '%s\n' "$CHANGED_FILES" | grep -Eq "$pattern"
}

COMPOSER_CHANGED=0
FRONTEND_CHANGED=0
MIGRATIONS_CHANGED=0
DB_RISK_CHANGED=0
WORKER_CHANGED=0

changed '(^|/)(composer\.json|composer\.lock)$' && COMPOSER_CHANGED=1
changed '^(resources/(css|js|views)/|vite\.config\.|package(-lock)?\.json$|tailwind\.config\.|postcss\.config\.)' && FRONTEND_CHANGED=1
changed '^database/migrations/' && MIGRATIONS_CHANGED=1
changed '^(database/migrations/|app/Models/|app/Services/.*(Inventory|Payout|Payroll|Show|Fulfillment)|app/Actions/)' && DB_RISK_CHANGED=1
changed '^(app/Jobs/|app/Listeners/|app/Events/|app/AI/|config/queue\.php|routes/console\.php)' && WORKER_CHANGED=1

maintenance_enabled=0
restore_app() {
  if [ "$maintenance_enabled" -eq 1 ]; then
    php artisan up >/dev/null 2>&1 || true
  fi
}
trap restore_app EXIT

echo "Deploying VortexOps $BRANCH ($ENVIRONMENT) from $APP_DIR"
echo "Commit: $(git rev-parse --short HEAD)"
if [ "$FULL_DEPLOY" -eq 1 ]; then
  echo "Deploy mode: FULL (previous revision unavailable)"
else
  echo "Deploy mode: SMART"
  echo "Changed files:"
  printf '%s\n' "$CHANGED_FILES"
fi

# Only use maintenance mode for changes that can alter schema/dependencies.
# Ordinary PHP/Blade/CSS fixes remain online during the short cache refresh.
if [ "$BRANCH" = "Production" ] && { [ "$FULL_DEPLOY" -eq 1 ] || [ "$COMPOSER_CHANGED" -eq 1 ] || [ "$MIGRATIONS_CHANGED" -eq 1 ]; }; then
  php artisan down --retry=60 --secret="vortexops-deploy" || true
  maintenance_enabled=1
fi

# Back up only before changes that can affect persistent data/schema. A UI or
# controller-only deploy should not wait for a database backup.
if [ "$BRANCH" = "Production" ] && { [ "$FULL_DEPLOY" -eq 1 ] || [ "$DB_RISK_CHANGED" -eq 1 ]; }; then
  php artisan db:backup --prune=30
fi

# Clear stale compiled state before swapping caches. This is intentionally done
# on every deploy because PHP/routes/config may change even when no build does.
php artisan optimize:clear

if [ "$COMPOSER_CHANGED" -eq 1 ]; then
  echo "Composer files changed: installing PHP dependencies"
  if [ "$BRANCH" = "Production" ]; then
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
  else
    composer install --no-interaction --prefer-dist --optimize-autoloader
  fi
else
  echo "Composer unchanged: skipping composer install"
fi

# Cache on Redis. Every page reads settings, permissions and modules through
# the cache, and on the database store each read is a MySQL query. Once a Redis
# server answers here, move the cache to it, but only after proving it can
# write, read and lock; otherwise put .env back. Sessions and queues stay where
# they are, so nobody is signed out. Opt out with VORTEX_REDIS_CACHE=off.
# Trimmed: a .env saved with Windows line endings or a trailing space would
# otherwise read as "database\r" and never match.
env_value() { { grep -E "^[[:space:]]*$1[[:space:]]*=" .env || true; } | tail -n1 | cut -d= -f2- | sed 's/[[:space:]]*#.*$//' | tr -d "\"'\r" | xargs; }
set_env() {
  if grep -qE "^[[:space:]]*$1[[:space:]]*=" .env; then sed -i "s#^[[:space:]]*$1[[:space:]]*=.*#$1=$2#" .env; else printf '\n%s=%s\n' "$1" "$2" >> .env; fi
}
CACHE_NOW="$(env_value CACHE_STORE)"; CACHE_NOW="${CACHE_NOW:-database}"
echo "Cache store: $CACHE_NOW"
if [ "$(env_value VORTEX_REDIS_CACHE)" = "off" ]; then
  echo "VORTEX_REDIS_CACHE=off: leaving the cache where it is"
elif [ "$CACHE_NOW" != "database" ] && [ "$CACHE_NOW" != "file" ]; then
  echo "Cache already on $CACHE_NOW: nothing to switch"
else
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

if [ "$FRONTEND_CHANGED" -eq 1 ]; then
  echo "Frontend/theme changed: rebuilding Vite assets"
  # node_modules is disposable on this server. Interrupted/concurrent npm
  # installs can leave directories that make npm ci fail with ENOTEMPTY.
  # Start clean so a frontend deploy is deterministic.
  rm -rf node_modules
  npm ci --no-audit --no-fund
  npm run build
  rm -rf node_modules
else
  echo "Frontend unchanged: keeping existing public/build assets"
fi

if [ "$MIGRATIONS_CHANGED" -eq 1 ]; then
  echo "Migrations changed: running database migrations"
  php artisan migrate --force
else
  echo "No migrations changed: skipping migrate"
fi

# Idempotent: keep the same encrypted Web Push keys across deployments.
if [ -f app/Console/Commands/SetupWebPush.php ]; then
  php artisan push:setup
fi

php artisan storage:link >/dev/null 2>&1 || true
php artisan filament:clear-cached-components || true

# Cache only the safe framework artifacts. Blade/Livewire views compile on
# demand because this app can exceed PCRE limits during a global view:cache.
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan filament:optimize

# Restart queues only when long-running worker code/config changed. Normal
# request/response PHP changes are picked up by PHP-FPM after the reload below.
if [ "$WORKER_CHANGED" -eq 1 ] || [ "$COMPOSER_CHANGED" -eq 1 ]; then
  php artisan queue:restart

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
else
  echo "Worker code unchanged: skipping worker restarts"
fi

# Reload PHP-FPM/Apache OPcache so changed application PHP is immediately live.
FPM_UNIT="$(systemctl list-units --type=service --all --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}' | head -n1 || true)"
if [ -n "$FPM_UNIT" ]; then
  sudo -n systemctl reload "$FPM_UNIT" 2>/dev/null || true
fi
sudo -n systemctl reload apache2 2>/dev/null || true

php artisan health:check >/dev/null

if [ "$maintenance_enabled" -eq 1 ]; then
  php artisan up
  maintenance_enabled=0
fi

echo "Bare-metal deploy complete: $BRANCH @ $(git rev-parse --short HEAD)"

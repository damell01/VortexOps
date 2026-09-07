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

npm ci --no-audit --no-fund
npm run build

# Runtime only needs public/build. Removing node_modules keeps the two
# bare-metal checkouts from wasting several GB on duplicate build dependencies.
rm -rf node_modules

php artisan migrate --force
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

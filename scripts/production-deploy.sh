#!/usr/bin/env bash

set -Eeuo pipefail

if [ "$(git rev-parse --abbrev-ref HEAD)" != "Production" ]; then
  echo "Production deploy must run from the Production checkout."
  exit 1
fi

read -r -p "Type DEPLOY to deploy Production: " CONFIRM
if [ "$CONFIRM" != "DEPLOY" ]; then
  echo "Deployment cancelled."
  exit 1
fi

php artisan db:backup --prune=30

git fetch origin Production
git reset --hard origin/Production

DEPLOY_BRANCH=Production DEPLOY_ENVIRONMENT=production \
  bash scripts/baremetal-deploy.sh Production

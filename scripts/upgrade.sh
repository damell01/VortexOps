#!/usr/bin/env bash
# VortexOps upgrade script — pull latest code and restart services
# Usage: sudo bash scripts/upgrade.sh [--docker] [--skip-assets]
set -euo pipefail

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GRN='\033[0;32m'; YLW='\033[1;33m'; BLU='\033[0;34m'
PRP='\033[0;35m'; NC='\033[0m'

h1()  { echo -e "\n${PRP}▸ $*${NC}"; }
ok()  { echo -e "  ${GRN}✓${NC} $*"; }
info(){ echo -e "  ${BLU}·${NC} $*"; }
warn(){ echo -e "  ${YLW}⚠${NC}  $*"; }
die() { echo -e "\n${RED}Error: $*${NC}" >&2; exit 1; }

APP_DIR="${VORTEXOPS_DIR:-/var/www/vortexops}"
DOCKER=false
SKIP_ASSETS=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    --docker)       DOCKER=true; shift ;;
    --skip-assets)  SKIP_ASSETS=true; shift ;;
    *) warn "Unknown argument: $1"; shift ;;
  esac
done

echo -e "\n${PRP}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${PRP}  VortexOps Upgrade${NC}"
echo -e "${PRP}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

if [[ "$DOCKER" == "true" ]]; then
  # ── Docker upgrade ──────────────────────────────────────────────────────────
  [[ -d "$APP_DIR" ]] || die "App directory not found: $APP_DIR"
  cd "$APP_DIR"

  h1 "Pulling latest code"
  git pull origin main
  COMMIT=$(git rev-parse --short HEAD)
  ok "Now at $COMMIT"

  h1 "Building new Docker image"
  docker compose build --pull
  ok "Image built"

  h1 "Restarting services (zero-downtime swap)"
  docker compose up -d --no-deps --remove-orphans app worker scheduler
  ok "Containers restarted"

  h1 "Running migrations"
  docker compose exec -T app php artisan migrate --force
  ok "Migrations complete"

  h1 "Clearing and warming caches"
  docker compose exec -T app php artisan optimize:clear
  docker compose exec -T app php artisan optimize
  ok "Caches warmed"

  h1 "Checking health"
  sleep 5
  docker compose ps
  docker compose exec -T app php artisan about --only=Environment

else
  # ── Bare metal upgrade ──────────────────────────────────────────────────────
  [[ "$(id -u)" -ne 0 ]] && die "Run as root or with sudo for bare-metal upgrade."
  [[ -d "$APP_DIR" ]] || die "App directory not found: $APP_DIR (set VORTEXOPS_DIR env var to override)"
  cd "$APP_DIR"

  h1 "Pulling latest code"
  BEFORE=$(git rev-parse --short HEAD)
  git pull origin main
  AFTER=$(git rev-parse --short HEAD)
  if [[ "$BEFORE" == "$AFTER" ]]; then
    info "Already up to date ($AFTER) — continuing anyway"
  else
    ok "$BEFORE → $AFTER"
  fi

  h1 "Enabling maintenance mode"
  sudo -u www-data php artisan down --retry=10 --render="errors::503"
  ok "Maintenance mode on"

  # Trap to bring app back up on any error
  trap 'echo -e "\n${RED}Upgrade failed — bringing app back online${NC}"; sudo -u www-data php artisan up' ERR

  h1 "Installing PHP dependencies"
  sudo -u www-data composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader -q
  ok "Composer packages installed"

  if [[ "$SKIP_ASSETS" == "false" ]]; then
    h1 "Building frontend assets"
    if [[ -f package-lock.json ]]; then
      sudo -u www-data npm ci --silent
    fi
    sudo -u www-data npm run build --silent
    ok "Frontend built"
  else
    info "Skipping asset build (--skip-assets)"
  fi

  h1 "Running migrations"
  sudo -u www-data php artisan migrate --force
  ok "Migrations complete"

  h1 "Clearing and warming caches"
  sudo -u www-data php artisan optimize:clear
  sudo -u www-data php artisan optimize
  ok "Caches warmed"

  h1 "Restarting queue worker"
  if systemctl is-active --quiet vortexops-worker; then
    systemctl restart vortexops-worker
    ok "Queue worker restarted"
  else
    warn "vortexops-worker service not running — start it with: systemctl start vortexops-worker"
  fi

  h1 "Taking app out of maintenance mode"
  sudo -u www-data php artisan up
  ok "App is live"

  trap - ERR
fi

# ── Summary ───────────────────────────────────────────────────────────────────
echo -e "\n${PRP}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GRN}  Upgrade complete${NC}"
echo -e "${PRP}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

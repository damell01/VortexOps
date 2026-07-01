#!/usr/bin/env bash
# =============================================================================
# VortexOps — VPS First-Time Setup
# Run once on a fresh Ubuntu 22.04 / 24.04 VPS as root or a sudo user.
# =============================================================================
set -euo pipefail

INSTALL_DIR="/opt/vortexops"
GHCR_IMAGE="ghcr.io/damell01/vortexops:latest"

echo "============================================================"
echo "  VortexOps VPS Setup"
echo "============================================================"
echo ""

# ── 1. System packages ────────────────────────────────────────────────────────
echo "→ Updating system packages…"
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    ufw \
    fail2ban

# ── 2. Docker ─────────────────────────────────────────────────────────────────
if ! command -v docker &>/dev/null; then
    echo "→ Installing Docker…"
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
else
    echo "→ Docker already installed: $(docker --version)"
fi

# Add current non-root user to docker group (if script run with sudo)
if [ "${SUDO_USER:-}" != "" ]; then
    usermod -aG docker "$SUDO_USER"
    echo "  Added $SUDO_USER to the docker group. Re-login to use docker without sudo."
fi

# ── 3. App directory ──────────────────────────────────────────────────────────
echo "→ Creating app directory at $INSTALL_DIR…"
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

# ── 4. docker-compose.yml ─────────────────────────────────────────────────────
# The CI/CD pipeline pushes docker-compose.yml here automatically on every deploy.
# If this is a first-time setup before the first deploy, grab it from the repo or
# copy it manually.
if [ ! -f "$INSTALL_DIR/docker-compose.yml" ]; then
    echo ""
    echo "  ⚠  docker-compose.yml not found."
    echo "  Copy it to $INSTALL_DIR/docker-compose.yml before running 'docker compose up'."
    echo "  (It will be pushed here automatically after the first GitHub Actions deploy.)"
    echo ""
fi

# ── 5. Environment file ───────────────────────────────────────────────────────
if [ ! -f "$INSTALL_DIR/.env.docker" ]; then
    echo ""
    echo "→ Creating .env.docker from template…"
    cat > "$INSTALL_DIR/.env.docker" <<'ENVFILE'
APP_NAME=VortexOps
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_OWNER_EMAIL=dbellcreations@gmail.com

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=vortexops
DB_USERNAME=vortexops
DB_PASSWORD=CHANGE_ME_DB_PASSWORD

DB_ROOT_PASSWORD=CHANGE_ME_ROOT_PASSWORD

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database

OLLAMA_BASE_URL=http://ollama:11434
OLLAMA_MODEL=llama3.2:3b
OLLAMA_VISION_MODEL=moondream
OLLAMA_TIMEOUT=60

CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_FROM_ADDRESS="ops@your-domain.com"
MAIL_FROM_NAME="VortexOps"

SCRAPER_API_TOKEN=CHANGE_ME_SCRAPER_TOKEN

RUN_MIGRATIONS=true
ENVFILE
    echo ""
    echo "  ✓ Created $INSTALL_DIR/.env.docker"
    echo ""
    echo "  *** Edit this file before starting the app: ***"
    echo "    nano $INSTALL_DIR/.env.docker"
    echo ""
    echo "  Required changes:"
    echo "    APP_KEY        — generate with: docker run --rm php:8.3-cli php artisan key:generate --show"
    echo "    APP_URL        — your actual domain (https://yourdomain.com)"
    echo "    APP_OWNER_EMAIL — your email for owner-only features"
    echo "    DB_PASSWORD    — strong random password"
    echo "    DB_ROOT_PASSWORD — strong random password (different from DB_PASSWORD)"
    echo "    SCRAPER_API_TOKEN — any random string (used by scraper webhook)"
    echo ""
    read -rp "Press Enter when you have edited .env.docker to continue, or Ctrl+C to stop here… "
fi

# ── 6. Firewall ───────────────────────────────────────────────────────────────
echo "→ Configuring firewall…"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
echo "  Firewall enabled: SSH, HTTP, HTTPS allowed."

# ── 7. Generate APP_KEY if not set ────────────────────────────────────────────
if grep -q "^APP_KEY=$" "$INSTALL_DIR/.env.docker" 2>/dev/null; then
    echo "→ Generating APP_KEY…"
    APP_KEY=$(docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));")
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" "$INSTALL_DIR/.env.docker"
    echo "  APP_KEY set."
fi

# ── 8. Pull and start services ────────────────────────────────────────────────
if [ -f "$INSTALL_DIR/docker-compose.yml" ]; then
    echo "→ Pulling Docker images…"
    docker pull "$GHCR_IMAGE" || echo "  (Could not pull image — run 'docker compose pull' after GitHub deploy)"

    echo "→ Starting core services (app, worker, scheduler, mysql, redis)…"
    docker compose -f "$INSTALL_DIR/docker-compose.yml" --env-file "$INSTALL_DIR/.env.docker" up -d \
        app worker scheduler ai-worker mysql redis

    echo ""
    echo "  Waiting 30 s for MySQL to become healthy…"
    sleep 30

    echo "→ Running migrations…"
    docker compose -f "$INSTALL_DIR/docker-compose.yml" exec -T app php artisan migrate --force

    echo "→ Seeding default data (roles, locations, admin user)…"
    docker compose -f "$INSTALL_DIR/docker-compose.yml" exec -T app php artisan db:seed --class=DefaultDataSeeder --force
    docker compose -f "$INSTALL_DIR/docker-compose.yml" exec -T app php artisan db:seed --class=SuperAdminSeeder --force

    echo "→ Warming caches…"
    docker compose -f "$INSTALL_DIR/docker-compose.yml" exec -T app php artisan optimize
    docker compose -f "$INSTALL_DIR/docker-compose.yml" exec -T app php artisan filament:optimize
fi

echo ""
echo "============================================================"
echo "  Setup complete!"
echo "============================================================"
echo ""
echo "  App URL:   $(grep APP_URL $INSTALL_DIR/.env.docker | cut -d= -f2)"
echo "  Admin:     admin@vortexbreaks.com / password"
echo "  Dev:       dev@vortexbreaks.com   / devpassword"
echo ""
echo "  ⚠  Change the default passwords immediately after first login."
echo ""
echo "  Next steps:"
echo "  1. Point your domain DNS A record to this server's IP."
echo "  2. Set up SSL — see deploy/nginx-ssl.md"
echo "  3. Pull AI models — see below."
echo "  4. Add GitHub secrets for CI/CD — see SETUP.md"
echo ""
echo "  To pull Ollama AI models:"
echo "    docker compose --profile ai up -d ollama"
echo "    docker compose exec ollama ollama pull llama3.2:3b"
echo "    docker compose exec ollama ollama pull moondream"
echo ""

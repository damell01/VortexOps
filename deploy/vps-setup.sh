#!/usr/bin/env bash
# VortexOps — Docker-based VPS setup (Ubuntu 22.04 / 24.04)
#
# Run once on a fresh VPS as root or a sudo user:
#   curl -fsSL https://raw.githubusercontent.com/damell01/vortexops/main/deploy/vps-setup.sh | bash
#   -- or --
#   bash deploy/vps-setup.sh
#
# What it does:
#   1. Installs Docker Engine + Docker Compose plugin
#   2. Installs Nginx + Certbot
#   3. Creates /opt/vortexops with the right ownership
#   4. Copies Nginx config and prompts for your domain/email
#   5. Obtains a Let's Encrypt certificate
#   6. Prints next steps

set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error() { echo -e "${RED}[ERR]${NC}   $*"; exit 1; }

[[ $EUID -ne 0 ]] && error "Run this script as root or with sudo."

# ── Inputs ────────────────────────────────────────────────────────────────────
read -rp "Your domain (e.g. ops.vortexbreaks.com): " DOMAIN
read -rp "Email for Let's Encrypt alerts: " LE_EMAIL
read -rp "Deploy user (will own /opt/vortexops) [ubuntu]: " DEPLOY_USER
DEPLOY_USER="${DEPLOY_USER:-ubuntu}"

# ── Docker ────────────────────────────────────────────────────────────────────
if ! command -v docker &>/dev/null; then
    info "Installing Docker Engine..."
    apt-get update -qq
    apt-get install -y --no-install-recommends ca-certificates curl gnupg
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
        | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    systemctl enable --now docker
    usermod -aG docker "$DEPLOY_USER"
    info "Docker installed ✓"
else
    info "Docker already installed — skipping."
fi

# ── Nginx + Certbot ───────────────────────────────────────────────────────────
info "Installing Nginx and Certbot..."
apt-get update -qq
apt-get install -y --no-install-recommends nginx certbot python3-certbot-nginx
systemctl enable --now nginx

# ── App directory ─────────────────────────────────────────────────────────────
info "Setting up /opt/vortexops..."
mkdir -p /opt/vortexops
chown "$DEPLOY_USER:$DEPLOY_USER" /opt/vortexops

# ── Nginx config ──────────────────────────────────────────────────────────────
info "Writing Nginx config for $DOMAIN..."
NGINX_CONF="$(dirname "$0")/nginx.conf"
if [[ -f "$NGINX_CONF" ]]; then
    sed "s/YOUR_DOMAIN/$DOMAIN/g" "$NGINX_CONF" > /etc/nginx/sites-available/vortexops
else
    # Inline fallback
    cat > /etc/nginx/sites-available/vortexops <<NGINXEOF
server {
    listen 80;
    server_name $DOMAIN;

    location /.well-known/acme-challenge/ { root /var/www/certbot; }
    location / { return 301 https://\$host\$request_uri; }
}

server {
    listen 443 ssl http2;
    server_name $DOMAIN;

    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 1d;

    add_header Strict-Transport-Security "max-age=63072000" always;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;

    client_max_body_size 64M;

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_read_timeout 120s;
    }
}
NGINXEOF
fi

ln -sf /etc/nginx/sites-available/vortexops /etc/nginx/sites-enabled/vortexops
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ── SSL certificate ───────────────────────────────────────────────────────────
info "Obtaining Let's Encrypt certificate for $DOMAIN..."
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$LE_EMAIL" --redirect
nginx -t && systemctl reload nginx

# ── Auto-renew (runs twice daily via systemd timer, already active) ───────────
systemctl is-active --quiet certbot.timer \
    && info "Certbot timer already active ✓" \
    || (systemctl enable --now certbot.timer && info "Certbot renewal timer enabled ✓")

# ── Firewall ──────────────────────────────────────────────────────────────────
if command -v ufw &>/dev/null; then
    ufw allow OpenSSH
    ufw allow "Nginx Full"
    ufw --force enable
    info "UFW firewall configured ✓"
fi

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  VPS setup complete!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Next steps (as the $DEPLOY_USER user):"
echo ""
echo "  1. cd /opt/vortexops"
echo "  2. Copy and fill your env file:"
echo "       cp /path/to/repo/.env.docker.example .env.docker"
echo "       nano .env.docker   # set APP_URL=https://$DOMAIN, DB passwords, etc."
echo "  3. Copy docker-compose.yml:"
echo "       cp /path/to/repo/docker-compose.yml ."
echo "  4. Pull and start:"
echo "       docker compose up -d"
echo "  5. Add GitHub Actions secrets (for auto-deploy on push to main):"
echo "       VPS_HOST=$DOMAIN"
echo "       VPS_USER=$DEPLOY_USER"
echo "       VPS_SSH_KEY=<your private key>"
echo "       GHCR_PAT=<GitHub personal access token with write:packages>"
echo ""
echo "  App will be live at: https://$DOMAIN"
echo ""

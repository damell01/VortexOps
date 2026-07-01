#!/usr/bin/env bash
# VortexOps — Bare-metal setup (Ubuntu 22.04 / 24.04, no Docker)
#
# Usage (as root):
#   bash deploy/bare-metal-setup.sh
#
# What it installs:
#   PHP 8.3 + Apache, MySQL 8.x, Node 22, Composer
#   Queue worker systemd service
#   Scheduler systemd service
#   Nginx reverse proxy + Certbot SSL
#
# After running, cd /var/www/vortexops and follow the printed instructions.

set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error() { echo -e "${RED}[ERR]${NC}   $*"; exit 1; }

[[ $EUID -ne 0 ]] && error "Run as root or with sudo."

# ── Inputs ────────────────────────────────────────────────────────────────────
read -rp "Your domain (e.g. ops.vortexbreaks.com): " DOMAIN
read -rp "Email for Let's Encrypt alerts: " LE_EMAIL
read -rp "MySQL root password to set: " MYSQL_ROOT_PW
read -rp "MySQL app password to set: " MYSQL_APP_PW
APP_DIR="/var/www/vortexops"

# ── System packages ───────────────────────────────────────────────────────────
info "Updating package list..."
apt-get update -qq

info "Installing prerequisites..."
apt-get install -y --no-install-recommends \
    ca-certificates curl gnupg lsb-release software-properties-common unzip git

# ── PHP 8.3 ───────────────────────────────────────────────────────────────────
info "Installing PHP 8.3..."
add-apt-repository ppa:ondrej/php -y
apt-get update -qq
apt-get install -y \
    php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-sqlite3 \
    php8.3-bcmath php8.3-exif php8.3-gd php8.3-intl php8.3-mbstring \
    php8.3-pcntl php8.3-xml php8.3-zip php8.3-curl php8.3-opcache

# OPcache — validate timestamps every 60 s (safe for live-edit deploys; set
# to 0 and reload Apache after each deploy for maximum speed on busy servers)
cat > /etc/php/8.3/mods-available/vortexops.ini <<'PHPINIEOF'
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
realpath_cache_size = 4096K
realpath_cache_ttl = 600

opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 60
opcache.save_comments = 1
opcache.fast_shutdown = 1
opcache.jit = tracing
opcache.jit_buffer_size = 64M
PHPINIEOF
phpenmod -v 8.3 vortexops
info "OPcache configured ✓"

# ── Apache ────────────────────────────────────────────────────────────────────
info "Installing Apache..."
apt-get install -y apache2 libapache2-mod-php8.3
a2enmod rewrite headers expires php8.3
systemctl enable --now apache2

# ── MySQL 8 ───────────────────────────────────────────────────────────────────
info "Installing MySQL 8..."
apt-get install -y mysql-server
systemctl enable --now mysql
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PW}';"
mysql -u root -p"${MYSQL_ROOT_PW}" <<SQL
CREATE DATABASE IF NOT EXISTS vortexops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'vortexops'@'localhost' IDENTIFIED BY '${MYSQL_APP_PW}';
GRANT ALL PRIVILEGES ON vortexops.* TO 'vortexops'@'localhost';
FLUSH PRIVILEGES;
SQL
info "MySQL configured ✓"

# ── Node 22 ───────────────────────────────────────────────────────────────────
info "Installing Node.js 22..."
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs

# ── Composer ─────────────────────────────────────────────────────────────────
if ! command -v composer &>/dev/null; then
    info "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# ── Nginx + Certbot ───────────────────────────────────────────────────────────
info "Installing Nginx and Certbot..."
apt-get install -y nginx certbot python3-certbot-nginx
systemctl enable --now nginx

# ── App directory ─────────────────────────────────────────────────────────────
info "Creating $APP_DIR..."
mkdir -p "$APP_DIR"
chown www-data:www-data "$APP_DIR"

# ── Apache vhost ──────────────────────────────────────────────────────────────
info "Writing Apache vhost..."
cat > /etc/apache2/sites-available/vortexops.conf <<APACHEEOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $APP_DIR/public

    <Directory $APP_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/vortexops_error.log
    CustomLog \${APACHE_LOG_DIR}/vortexops_access.log combined
</VirtualHost>
APACHEEOF

a2ensite vortexops
a2dissite 000-default || true
apache2ctl configtest && systemctl reload apache2

# ── Nginx as SSL reverse proxy (Apache on 8081) ───────────────────────────────
# Switch Apache to port 8081 so Nginx can own 80/443
sed -i 's/Listen 80$/Listen 8081/' /etc/apache2/ports.conf
sed -i 's/\*:80>/*:8081>/' /etc/apache2/sites-available/vortexops.conf
sed -i 's/\*:80>/*:8081>/' /etc/apache2/sites-available/000-default.conf 2>/dev/null || true
systemctl reload apache2

cat > /etc/nginx/sites-available/vortexops <<NGINXEOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    location /.well-known/acme-challenge/ { root /var/www/certbot; }
    location / { return 301 https://\$host\$request_uri; }
}
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $DOMAIN;

    # Managed by Certbot
    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
    ssl_prefer_server_ciphers off;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains" always;
    add_header X-Frame-Options           SAMEORIGIN                            always;
    add_header X-Content-Type-Options    nosniff                               always;
    add_header Referrer-Policy           "no-referrer-when-downgrade"          always;

    client_max_body_size 64M;

    # Serve static files from disk — skips Apache entirely for CSS/JS/images
    root $APP_DIR/public;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_min_length 256;
    gzip_types text/plain text/css text/xml application/json application/javascript
               application/xml+rss application/atom+xml image/svg+xml;

    # Vite build assets — content-hashed, safe to cache for 1 year
    location ^~ /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Laravel storage/public symlink
    location ^~ /storage/ {
        expires 7d;
        add_header Cache-Control "public";
        access_log off;
    }

    location = /health {
        access_log off;
        proxy_pass http://127.0.0.1:8081;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location / {
        proxy_pass         http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header   Host              \$host;
        proxy_set_header   X-Real-IP         \$remote_addr;
        proxy_set_header   X-Forwarded-For   \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_set_header   X-Forwarded-Port  443;
        proxy_set_header   Upgrade           \$http_upgrade;
        proxy_set_header   Connection        "upgrade";
        proxy_read_timeout  120s;
        proxy_send_timeout  120s;
        proxy_connect_timeout 10s;
        proxy_buffering    on;
        proxy_buffers      16 16k;
        proxy_buffer_size  32k;
    }

    access_log /var/log/nginx/vortexops_access.log combined;
    error_log  /var/log/nginx/vortexops_error.log  warn;
}
NGINXEOF

ln -sf /etc/nginx/sites-available/vortexops /etc/nginx/sites-enabled/vortexops
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ── SSL ───────────────────────────────────────────────────────────────────────
info "Obtaining SSL certificate..."
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$LE_EMAIL" --redirect
nginx -t && systemctl reload nginx

# ── Systemd: queue worker ─────────────────────────────────────────────────────
info "Installing queue worker service..."
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
if [[ -f "$SCRIPT_DIR/vortexops-worker.service" ]]; then
    sed "s|APP_DIR|$APP_DIR|g" "$SCRIPT_DIR/vortexops-worker.service" \
        > /etc/systemd/system/vortexops-worker.service
else
    cat > /etc/systemd/system/vortexops-worker.service <<SVCEOF
[Unit]
Description=VortexOps Queue Worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-jobs=500 --max-time=3600
Restart=always
RestartSec=5s
StartLimitIntervalSec=60

[Install]
WantedBy=multi-user.target
SVCEOF
fi

# ── Systemd: scheduler ────────────────────────────────────────────────────────
info "Installing scheduler service + timer..."
cat > /etc/systemd/system/vortexops-scheduler.service <<SVCEOF
[Unit]
Description=VortexOps Scheduler (single run)
After=network.target mysql.service

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php artisan schedule:run --no-interaction
SVCEOF

cat > /etc/systemd/system/vortexops-scheduler.timer <<SVCEOF
[Unit]
Description=VortexOps Scheduler (every minute)

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min
Unit=vortexops-scheduler.service

[Install]
WantedBy=timers.target
SVCEOF

systemctl daemon-reload
systemctl enable --now vortexops-worker vortexops-scheduler.timer

# ── UFW ───────────────────────────────────────────────────────────────────────
if command -v ufw &>/dev/null; then
    ufw allow OpenSSH
    ufw allow "Nginx Full"
    ufw --force enable
fi

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  Bare-metal setup complete!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "Next steps (deploy your code):"
echo ""
echo "  1. Clone the repo (as www-data or a deploy user with write access):"
echo "       git clone https://github.com/damell01/vortexops.git $APP_DIR"
echo "       cd $APP_DIR"
echo ""
echo "  2. Install dependencies and build assets:"
echo "       composer install --no-dev --optimize-autoloader"
echo "       npm ci && npm run build"
echo ""
echo "  3. Configure the environment:"
echo "       cp .env.production.example .env"
echo "       nano .env   # set APP_URL=https://$DOMAIN, DB_PASSWORD=$MYSQL_APP_PW, APP_KEY, etc."
echo "       php artisan key:generate"
echo ""
echo "  4. Run migrations and seed:"
echo "       php artisan migrate --force --seed"
echo "       php artisan storage:link"
echo "       php artisan optimize"
echo "       php artisan filament:optimize"
echo ""
echo "  5. Fix permissions:"
echo "       chown -R www-data:www-data $APP_DIR/storage $APP_DIR/bootstrap/cache"
echo ""
echo "  6. After each future code deploy (git pull), clear OPcache:"
echo "       php artisan optimize"
echo "       systemctl reload apache2"
echo ""
echo "  App live at: https://$DOMAIN"
echo "  Worker:      systemctl status vortexops-worker"
echo "  Scheduler:   systemctl status vortexops-scheduler.timer"
echo ""

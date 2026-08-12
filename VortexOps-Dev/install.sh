#!/usr/bin/env bash
# VortexOps bare-metal installer
# Tested on Ubuntu 24.04 LTS. Run as root or with sudo.
# Usage: sudo bash install.sh [--domain your-domain.com] [--owner you@example.com]
set -euo pipefail

# ── Colours ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GRN='\033[0;32m'; YLW='\033[1;33m'; BLU='\033[0;34m'
PRP='\033[0;35m'; NC='\033[0m'

h1()  { echo -e "\n${PRP}▸ $*${NC}"; }
ok()  { echo -e "  ${GRN}✓${NC} $*"; }
info(){ echo -e "  ${BLU}·${NC} $*"; }
warn(){ echo -e "  ${YLW}⚠${NC}  $*"; }
die() { echo -e "\n${RED}Error: $*${NC}" >&2; exit 1; }

# ── Defaults ─────────────────────────────────────────────────────────────────
DOMAIN=""
OWNER_EMAIL=""
APP_DIR="/var/www/vortexops"
DB_NAME="vortexops"
DB_USER="vortexops"
REPO="https://github.com/damell01/vortexops.git"

# ── Argument parsing ──────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="$2"; shift 2 ;;
    --owner)  OWNER_EMAIL="$2"; shift 2 ;;
    *) warn "Unknown argument: $1"; shift ;;
  esac
done

# ── Prompts ───────────────────────────────────────────────────────────────────
echo -e "\n${PRP}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${PRP}  VortexOps Installer${NC}"
echo -e "${PRP}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

[[ -z "$DOMAIN" ]]      && read -rp "  Domain (e.g. ops.vortexbreaks.com): " DOMAIN
[[ -z "$OWNER_EMAIL" ]] && read -rp "  Owner email (super-admin): " OWNER_EMAIL

# Generate passwords
DB_PASS=$(openssl rand -base64 20 | tr -dc 'a-zA-Z0-9' | head -c 24)
DB_ROOT_PASS=$(openssl rand -base64 20 | tr -dc 'a-zA-Z0-9' | head -c 24)

# ── Guard: must be root ───────────────────────────────────────────────────────
[[ "$(id -u)" -ne 0 ]] && die "Run this script as root or with sudo."

# ── 1. System packages ────────────────────────────────────────────────────────
h1 "Installing system packages"
apt-get update -qq
apt-get install -y --no-install-recommends \
  git curl unzip ca-certificates gnupg lsb-release \
  php8.3 php8.3-cli php8.3-fpm \
  php8.3-bcmath php8.3-curl php8.3-exif php8.3-gd \
  php8.3-intl php8.3-mbstring php8.3-pcntl \
  php8.3-pdo php8.3-mysql php8.3-redis \
  php8.3-sqlite3 php8.3-xml php8.3-zip \
  apache2 libapache2-mod-php8.3 \
  redis-server \
  2>/dev/null
ok "System packages installed"

# ── 2. MySQL 8.4 ─────────────────────────────────────────────────────────────
h1 "Installing MySQL 8.4"
if ! command -v mysqld &>/dev/null; then
  curl -fsSL https://dev.mysql.com/get/mysql-apt-config_0.8.33-1_all.deb -o /tmp/mysql-apt-config.deb
  DEBIAN_FRONTEND=noninteractive dpkg -i /tmp/mysql-apt-config.deb
  apt-get update -qq
  apt-get install -y mysql-server 2>/dev/null
fi

# Set root password and create DB
mysql -u root <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PASS}';
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "MySQL configured (root pass saved to /root/.vortexops-db-creds)"
echo "DB_ROOT_PASSWORD=${DB_ROOT_PASS}" > /root/.vortexops-db-creds
echo "DB_PASSWORD=${DB_PASS}"          >> /root/.vortexops-db-creds
chmod 600 /root/.vortexops-db-creds

# Tune MySQL for ~8 GB VPS: small buffer pool, cap connections
cat > /etc/mysql/conf.d/vortexops.cnf <<'MYCNF'
[mysqld]
innodb_buffer_pool_size   = 256M
innodb_log_file_size      = 64M
max_connections           = 100
thread_cache_size         = 8
query_cache_type          = 0
tmp_table_size            = 32M
max_heap_table_size       = 32M
slow_query_log            = 1
slow_query_log_file       = /var/log/mysql/slow.log
long_query_time           = 2
MYCNF
systemctl restart mysql
ok "MySQL tuned for 8 GB VPS"

# ── 3. Ollama ────────────────────────────────────────────────────────────────
h1 "Installing Ollama"
if ! command -v ollama &>/dev/null; then
  curl -fsSL https://ollama.com/install.sh | sh
fi
ok "Ollama $(ollama --version 2>/dev/null || echo 'installed')"

# Ollama install.sh already creates a systemd service; make sure it's running
systemctl enable --now ollama 2>/dev/null || true
sleep 2  # give the daemon a moment to start

info "Pulling gemma2:2b model (fastest quality model for 8 GB VPS — ~1.6 GB download)..."
ollama pull gemma2:2b
ok "gemma2:2b model ready"

# ── 4. Composer ───────────────────────────────────────────────────────────────
h1 "Installing Composer"
if ! command -v composer &>/dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
ok "Composer $(composer --version --no-ansi | head -1)"

# ── 5. Node.js 22 + Playwright ───────────────────────────────────────────────
h1 "Installing Node.js 22 and Playwright"
if ! command -v node &>/dev/null || [[ "$(node -e 'process.stdout.write(process.version.split(".")[0].slice(1))')" -lt 22 ]]; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y nodejs 2>/dev/null
fi
ok "Node $(node --version)"

# Install Playwright globally where scraper expects it
npm install -g playwright 2>/dev/null
npx playwright install chromium --with-deps 2>/dev/null
ok "Playwright Chromium installed"

# ── 6. Clone repository ───────────────────────────────────────────────────────
h1 "Cloning VortexOps"
if [[ -d "$APP_DIR/.git" ]]; then
  info "Repository already exists — pulling latest"
  git -C "$APP_DIR" pull origin main
else
  git clone "$REPO" "$APP_DIR"
fi
ok "Repository at $APP_DIR"

cd "$APP_DIR"

# ── 6. PHP dependencies ───────────────────────────────────────────────────────
h1 "Installing PHP dependencies"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader -q
ok "Composer packages installed"

# ── 7. Node dependencies + frontend build ────────────────────────────────────
h1 "Building frontend assets"
npm ci --silent
npm run build --silent
ok "Frontend built"

# ── 8. Environment file ───────────────────────────────────────────────────────
h1 "Creating .env"
if [[ ! -f "$APP_DIR/.env" ]]; then
  cp "$APP_DIR/.env.example" "$APP_DIR/.env"
fi

APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")

# Write values into .env using sed
set_env() {
  local key="$1" val="$2"
  if grep -q "^${key}=" "$APP_DIR/.env"; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$APP_DIR/.env"
  else
    echo "${key}=${val}" >> "$APP_DIR/.env"
  fi
}

set_env APP_KEY         "$APP_KEY"
set_env APP_ENV         "production"
set_env APP_DEBUG       "false"
set_env APP_URL         "https://${DOMAIN}"
set_env APP_OWNER_EMAIL "$OWNER_EMAIL"

set_env DB_CONNECTION   "mysql"
set_env DB_HOST         "127.0.0.1"
set_env DB_PORT         "3306"
set_env DB_DATABASE     "$DB_NAME"
set_env DB_USERNAME     "$DB_USER"
set_env DB_PASSWORD     "$DB_PASS"

set_env QUEUE_CONNECTION "database"
set_env CACHE_STORE      "database"
set_env SESSION_DRIVER   "database"

set_env OLLAMA_BASE_URL   "http://127.0.0.1:11434"
set_env OLLAMA_MODEL      "gemma2:2b"
set_env OLLAMA_TIMEOUT    "120"

set_env MAIL_MAILER      "log"
set_env MAIL_FROM_ADDRESS "noreply@${DOMAIN}"

ok ".env written"
warn "Edit $APP_DIR/.env to add WHATNOT_EMAIL, WHATNOT_PASSWORD, and MAIL_* settings"

# ── 9. Bootstrap Laravel ──────────────────────────────────────────────────────
h1 "Running migrations and seeding"
php artisan migrate --force --seed
php artisan storage:link
php artisan optimize
ok "Application bootstrapped"

# ── 10. File permissions ──────────────────────────────────────────────────────
h1 "Setting file permissions"
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
ok "Permissions set"

# ── 11. Apache virtual host ───────────────────────────────────────────────────
h1 "Configuring Apache"
a2enmod rewrite headers expires deflate -q

cat > /etc/apache2/sites-available/vortexops.conf <<VHOST
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Disable gzip + proxy buffering for Server-Sent Events (Livewire AI streaming)
    <IfModule mod_deflate.c>
        SetEnvIfNoCase Content-Type "text/event-stream" no-gzip dont-vary
    </IfModule>
    Header always set X-Accel-Buffering "no"

    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    ErrorLog  \${APACHE_LOG_DIR}/vortexops-error.log
    CustomLog \${APACHE_LOG_DIR}/vortexops-access.log combined

    # Redirect to HTTPS once you have a cert
    # Redirect permanent / https://${DOMAIN}/
</VirtualHost>
VHOST

a2dissite 000-default.conf 2>/dev/null || true
a2ensite vortexops.conf
systemctl reload apache2
ok "Apache configured with SSE streaming support (HTTP only — add SSL with certbot)"

# ── 12. Cron (scheduler) ─────────────────────────────────────────────────────
h1 "Installing cron schedule"
CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
(crontab -u www-data -l 2>/dev/null | grep -v "schedule:run"; echo "$CRON_LINE") \
  | crontab -u www-data -
ok "Cron entry added for www-data"

# ── 13. Queue workers (systemd) ───────────────────────────────────────────────
h1 "Installing queue worker services"

# Default queue worker (notifications, exports, general jobs)
cat > /etc/systemd/system/vortexops-worker.service <<UNIT
[Unit]
Description=VortexOps queue worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work \\
    --sleep=3 --tries=3 --timeout=120 \\
    --max-jobs=500 --max-time=3600
Restart=on-failure
RestartSec=5s

[Install]
WantedBy=multi-user.target
UNIT

# AI queue worker (inventory mapping jobs — long timeout, single try)
cat > /etc/systemd/system/vortexops-ai-worker.service <<UNIT
[Unit]
Description=VortexOps AI queue worker
After=network.target mysql.service ollama.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work \\
    --queue=ai --sleep=5 --tries=1 --timeout=300 \\
    --max-jobs=50 --max-time=3600
Restart=on-failure
RestartSec=10s

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now vortexops-worker
systemctl enable --now vortexops-ai-worker
ok "Queue workers enabled (default + ai)"

# ── Done ──────────────────────────────────────────────────────────────────────
echo -e "\n${PRP}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GRN}  VortexOps installed successfully!${NC}"
echo -e "${PRP}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"
echo -e "  App directory : ${BLU}${APP_DIR}${NC}"
echo -e "  URL           : ${BLU}http://${DOMAIN}${NC}"
echo -e "  Owner email   : ${BLU}${OWNER_EMAIL}${NC}"
echo ""
echo -e "  ${YLW}Next steps:${NC}"
echo -e "  1. Edit ${APP_DIR}/.env — add WHATNOT_EMAIL, WHATNOT_PASSWORD, MAIL_* settings"
echo -e "  2. Get a TLS cert:  ${BLU}certbot --apache -d ${DOMAIN}${NC}"
echo -e "  3. Open http://${DOMAIN} → Register with ${OWNER_EMAIL}"
echo -e "  4. Grant admin:     ${BLU}php artisan tinker --execute=\"App\\\\Models\\\\User::where('email','${OWNER_EMAIL}')->first()->assignRole('admin');\"${NC}"
echo -e "  5. Settings → Admin Modules → enable 'AI' to activate Vortex AI chat"
echo -e "  6. Settings → Add Whatnot channels → Test connection → Import\n"
echo -e "  Ollama model  : ${BLU}gemma2:2b${NC}  (change via OLLAMA_MODEL in .env)"
echo -e "  DB credentials: ${BLU}/root/.vortexops-db-creds${NC}\n"

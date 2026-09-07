#!/usr/bin/env bash

set -Eeuo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "Run with sudo/root."
  exit 1
fi

read -r -p "Dev domain [dev.vortexops.tech]: " DEV_DOMAIN
DEV_DOMAIN="${DEV_DOMAIN:-dev.vortexops.tech}"
read -r -p "Dev MySQL database [vortexops_dev]: " DEV_DB
DEV_DB="${DEV_DB:-vortexops_dev}"
read -r -p "Dev MySQL user [vortexops_dev]: " DEV_DB_USER
DEV_DB_USER="${DEV_DB_USER:-vortexops_dev}"
read -r -s -p "Dev MySQL password: " DEV_DB_PASSWORD
echo

if [ -z "$DEV_DOMAIN" ] || [ -z "$DEV_DB_PASSWORD" ]; then
  echo "Domain and DB password are required."
  exit 1
fi

APP_DIR=/var/www/vortexops-dev
REPO_URL=https://github.com/damell01/VortexOps.git

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DEV_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DEV_DB_USER}'@'localhost' IDENTIFIED BY '${DEV_DB_PASSWORD}';
ALTER USER '${DEV_DB_USER}'@'localhost' IDENTIFIED BY '${DEV_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DEV_DB}\`.* TO '${DEV_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

if [ ! -d "$APP_DIR/.git" ]; then
  rm -rf "$APP_DIR"
  git clone --branch Dev --single-branch "$REPO_URL" "$APP_DIR"
else
  cd "$APP_DIR"
  git fetch origin Dev
  git checkout -B Dev origin/Dev
fi

cd "$APP_DIR"

if [ ! -f .env ]; then
  cp .env.example .env
fi

set_env() {
  local key="$1" value="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

set_env APP_NAME '"VortexOps Dev"'
set_env APP_ENV staging
set_env APP_DEBUG false
set_env APP_URL "https://${DEV_DOMAIN}"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DEV_DB"
set_env DB_USERNAME "$DEV_DB_USER"
set_env DB_PASSWORD "$DEV_DB_PASSWORD"
set_env CACHE_PREFIX vortexops_dev_
set_env SESSION_COOKIE vortexops_dev_session
set_env MAIL_MAILER log
set_env WHATNOT_SCHEDULE_ENABLED false
set_env WHATNOT_STATE_DIR "$APP_DIR/storage/whatnot-channels"
set_env WHATNOT_SCRAPLING_DIAGNOSTICS_DIR "$APP_DIR/storage/logs/whatnot-scrapling"

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules
php artisan migrate --force
php artisan storage:link || true
php artisan optimize
php artisan filament:optimize

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

cat > /etc/apache2/sites-available/vortexops-dev.conf <<APACHE
<VirtualHost *:8081>
    ServerName ${DEV_DOMAIN}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/vortexops-dev_error.log
    CustomLog \${APACHE_LOG_DIR}/vortexops-dev_access.log combined
</VirtualHost>
APACHE

a2ensite vortexops-dev
apache2ctl configtest
systemctl reload apache2

cat > /etc/nginx/sites-available/vortexops-dev <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DEV_DOMAIN};

    location /.well-known/acme-challenge/ { root /var/www/certbot; }
    location / { return 301 https://\$host\$request_uri; }
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${DEV_DOMAIN};

    ssl_certificate /etc/letsencrypt/live/${DEV_DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DEV_DOMAIN}/privkey.pem;

    client_max_body_size 64M;
    root ${APP_DIR}/public;

    location ^~ /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ^~ /storage/ {
        expires 7d;
        access_log off;
    }

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 120s;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/vortexops-dev /etc/nginx/sites-enabled/vortexops-dev

certbot --nginx -d "$DEV_DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email || true
nginx -t
systemctl reload nginx

make_unit() {
  local source="$1" target="$2"
  sed "s|APP_DIR|${APP_DIR}|g" "$source" > "/etc/systemd/system/${target}.service"
}

make_unit deploy/vortexops-queue.service vortexops-dev-worker
make_unit deploy/vortexops-ai-worker.service vortexops-dev-ai-worker
make_unit deploy/vortexops-scheduler.service vortexops-dev-scheduler

systemctl daemon-reload
systemctl enable --now vortexops-dev-worker vortexops-dev-ai-worker vortexops-dev-scheduler

cat <<EOF

Dev bare-metal environment created.
URL: https://${DEV_DOMAIN}
Path: ${APP_DIR}
Branch: Dev
DB: ${DEV_DB}

Important defaults:
- WHATNOT_SCHEDULE_ENABLED=false
- MAIL_MAILER=log
- Separate cache/session namespace
- Separate Whatnot state/log paths

Before enabling real Dev scraper runs, authenticate the Dev profile separately
and turn WHATNOT_SCHEDULE_ENABLED on deliberately.
EOF

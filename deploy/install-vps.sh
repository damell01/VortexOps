#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run this script as root: sudo bash deploy/install-vps.sh"
    exit 1
fi

APP_NAME="${APP_NAME:-VortexOps}"
APP_SLUG="${APP_SLUG:-vortexops}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
SERVER_NAME="${SERVER_NAME:-_}"
PHP_VERSION="${PHP_VERSION:-8.3}"
DB_NAME="${DB_NAME:-vortexops}"
DB_USER="${DB_USER:-vortexops}"
DB_PASSWORD="${DB_PASSWORD:-change-me-now}"
ENABLE_TLS="${ENABLE_TLS:-false}"
ADMIN_EMAIL="${ADMIN_EMAIL:-}"
INSTALL_OLLAMA="${INSTALL_OLLAMA:-false}"
OLLAMA_MODEL="${OLLAMA_MODEL:-llama3.2}"
APP_SCHEME="http"

if [[ "${ENABLE_TLS}" == "true" && "${SERVER_NAME}" != "_" ]]; then
    APP_SCHEME="https"
fi

export SERVER_NAME DB_NAME DB_USER DB_PASSWORD APP_SCHEME

export DEBIAN_FRONTEND=noninteractive

echo "Installing OS packages..."
apt-get update
apt-get install -y software-properties-common curl ca-certificates gnupg lsb-release
add-apt-repository -y ppa:ondrej/php

if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
fi

apt-get update
apt-get install -y \
    git \
    unzip \
    nginx \
    mysql-server \
    redis-server \
    supervisor \
    certbot \
    python3-certbot-nginx \
    composer \
    nodejs \
    "php${PHP_VERSION}" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-redis" \
    "php${PHP_VERSION}-sqlite3" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-zip"

systemctl enable nginx
systemctl enable "php${PHP_VERSION}-fpm"
systemctl enable mysql
systemctl enable redis-server

echo "Configuring MySQL database..."
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "Installing PHP dependencies..."
cd "${APP_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction

echo "Installing frontend dependencies..."
npm ci
npm run build

if [[ ! -f "${APP_DIR}/.env" ]]; then
    cp "${APP_DIR}/.env.production.example" "${APP_DIR}/.env"
fi

if ! grep -q '^APP_KEY=base64:' "${APP_DIR}/.env"; then
    php artisan key:generate --force
fi

php -r '
$path = ".env";
$content = file_get_contents($path);
$pairs = [
    "APP_ENV" => "production",
    "APP_DEBUG" => "false",
    "APP_URL" => getenv("SERVER_NAME") && getenv("SERVER_NAME") !== "_" ? getenv("APP_SCHEME") . "://" . getenv("SERVER_NAME") : "http://server.local",
    "DB_CONNECTION" => "mysql",
    "DB_HOST" => "127.0.0.1",
    "DB_PORT" => "3306",
    "DB_DATABASE" => getenv("DB_NAME"),
    "DB_USERNAME" => getenv("DB_USER"),
    "DB_PASSWORD" => getenv("DB_PASSWORD"),
    "QUEUE_CONNECTION" => "database",
    "SESSION_DRIVER" => "database",
    "CACHE_STORE" => "database",
    "FILESYSTEM_DISK" => "public",
];
foreach ($pairs as $key => $value) {
    $pattern = "/^" . preg_quote($key, "/") . "=.*/m";
    $line = $key . "=" . $value;
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, $line, $content);
    } else {
        $content .= PHP_EOL . $line;
    }
}
file_put_contents($path, $content);
'

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache
chown -R "${APP_USER}:${APP_GROUP}" "${APP_DIR}"
chmod -R ug+rwx storage bootstrap/cache

echo "Running Laravel setup..."
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Installing systemd queue worker..."
sed \
    -e "s#__APP_DIR__#${APP_DIR}#g" \
    -e "s#__APP_USER__#${APP_USER}#g" \
    -e "s#__APP_GROUP__#${APP_GROUP}#g" \
    "${APP_DIR}/deploy/systemd/vortexops-queue.service" \
    > /etc/systemd/system/${APP_SLUG}-queue.service

systemctl daemon-reload
systemctl enable "${APP_SLUG}-queue.service"
systemctl restart "${APP_SLUG}-queue.service"

echo "Installing nginx site..."
sed \
    -e "s#__SERVER_NAME__#${SERVER_NAME}#g" \
    -e "s#__APP_DIR__#${APP_DIR}#g" \
    -e "s#__APP_SLUG__#${APP_SLUG}#g" \
    -e "s#__PHP_VERSION__#${PHP_VERSION}#g" \
    "${APP_DIR}/deploy/nginx.vhost.conf" \
    > /etc/nginx/sites-available/${APP_SLUG}.conf

ln -sf /etc/nginx/sites-available/${APP_SLUG}.conf /etc/nginx/sites-enabled/${APP_SLUG}.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

if [[ "${INSTALL_OLLAMA}" == "true" ]]; then
    echo "Installing Ollama..."
    curl -fsSL https://ollama.com/install.sh | sh
    systemctl enable ollama || true
    systemctl start ollama || true
    ollama pull "${OLLAMA_MODEL}" || true
fi

if [[ "${ENABLE_TLS}" == "true" && "${SERVER_NAME}" != "_" && -n "${ADMIN_EMAIL}" ]]; then
    echo "Requesting Let's Encrypt certificate..."
    certbot --nginx -d "${SERVER_NAME}" --non-interactive --agree-tos -m "${ADMIN_EMAIL}" --redirect
fi

echo
echo "Deployment complete."
echo "App directory: ${APP_DIR}"
echo "Site: ${SERVER_NAME}"
echo "Queue service: systemctl status ${APP_SLUG}-queue.service"

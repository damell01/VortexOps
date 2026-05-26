# VortexOps Deployment Guide

This repo now includes two supported deployment paths:

1. Native Ubuntu VPS deployment with Nginx, PHP-FPM, MySQL, Redis, Node, Composer, and a systemd queue worker.
2. Docker deployment using a production image plus MySQL and a dedicated worker container.

The app is a Laravel 13 + Filament 5 project with:

- PHP 8.3
- MySQL in production
- database-backed queues, sessions, and cache
- a long-running queue worker
- optional Ollama for AI features

## Production Checklist

Before deploying, decide these values:

- domain name
- database name, user, and password
- whether AI features should be enabled
- whether Ollama will run on the same server or elsewhere

Use [.env.production.example](/c:/Users/Shild/Downloads/VortexOps/.env.production.example) as the starting point for production configuration.

## Option 1: Ubuntu VPS

### What the installer does

The VPS installer script:

- installs PHP 8.3, Nginx, MySQL, Redis, Composer, Node.js, and Certbot
- installs Composer and npm dependencies
- builds frontend assets
- creates or updates `.env`
- creates the MySQL database and user
- runs migrations and seeders
- configures Nginx
- installs a systemd queue worker
- optionally installs Ollama and a model
- optionally requests a Let's Encrypt certificate

### Recommended server

- Ubuntu 24.04 LTS
- 2 vCPU / 4 GB RAM minimum
- more memory if Ollama will run locally

### Fast path

1. Clone the repo onto the VPS.
2. From the repo root, run:

```bash
sudo APP_DIR=/var/www/vortexops \
APP_SLUG=vortexops \
SERVER_NAME=ops.example.com \
DB_NAME=vortexops \
DB_USER=vortexops \
DB_PASSWORD='change-this-password' \
ENABLE_TLS=true \
ADMIN_EMAIL=ops@example.com \
bash deploy/install-vps.sh
```

### Notes about the installer

- `APP_DIR` should point at the deployed repo location.
- The script assumes it is being run from inside that repo.
- `db:seed --force` is included so a fresh production install gets roles, default records, and the default admin users from the current seeders.
- If you do not want demo data in production, remove or adjust the demo seeder before running the installer.

### Manual post-install checks

Run these after the script completes:

```bash
systemctl status nginx
systemctl status php8.3-fpm
systemctl status vortexops-queue
php artisan about
```

Open the site and confirm:

- login page loads
- static assets are present
- queue worker is running
- `storage/logs/laravel.log` is clean

### Files involved

- Installer: [deploy/install-vps.sh](/c:/Users/Shild/Downloads/VortexOps/deploy/install-vps.sh)
- Nginx template: [deploy/nginx.vhost.conf](/c:/Users/Shild/Downloads/VortexOps/deploy/nginx.vhost.conf)
- Queue service template: [deploy/systemd/vortexops-queue.service](/c:/Users/Shild/Downloads/VortexOps/deploy/systemd/vortexops-queue.service)

## Option 2: Docker

### Included artifacts

- Production image: [Dockerfile](/c:/Users/Shild/Downloads/VortexOps/Dockerfile)
- Compose stack: [docker-compose.yml](/c:/Users/Shild/Downloads/VortexOps/docker-compose.yml)
- Container entrypoint: [docker/php-entrypoint.sh](/c:/Users/Shild/Downloads/VortexOps/docker/php-entrypoint.sh)
- Docker env template: [.env.docker.example](/c:/Users/Shild/Downloads/VortexOps/.env.docker.example)

### What the Docker stack includes

- `app`: Apache + PHP 8.3 container serving Laravel from `public/`
- `worker`: dedicated `php artisan queue:work` container
- `mysql`: MySQL 8.4
- `ollama`: optional profile for AI features

### First-time setup

1. Create a Docker env file:

```bash
cp .env.docker.example .env.docker
```

2. Edit `.env.docker` and set at least:

- `APP_KEY`
- `APP_URL`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `SCRAPER_API_TOKEN`
- `OLLAMA_BASE_URL` if you are not using the optional local Ollama container

3. Generate an app key if needed:

```bash
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli php artisan key:generate --show
```

Copy that value into `.env.docker` as `APP_KEY=base64:...`

### Build and start

Without Ollama:

```bash
docker compose --env-file .env.docker up -d --build
```

With Ollama:

```bash
docker compose --env-file .env.docker --profile ai up -d --build
```

### Pull a local Ollama model

If you started the `ai` profile and want local AI support:

```bash
docker compose --env-file .env.docker exec ollama ollama pull llama3.2
```

If you use the bundled Ollama service, set:

```env
OLLAMA_BASE_URL=http://ollama:11434
OLLAMA_MODEL=llama3.2
```

### Useful Docker commands

```bash
docker compose --env-file .env.docker logs -f app
docker compose --env-file .env.docker logs -f worker
docker compose --env-file .env.docker exec app php artisan about
docker compose --env-file .env.docker exec app php artisan migrate --force
docker compose --env-file .env.docker exec app php artisan db:seed --force
```

### Publishing the image

To build and tag an image for a registry:

```bash
docker build -t ghcr.io/your-org/vortexops:latest .
```

Then push it:

```bash
docker push ghcr.io/your-org/vortexops:latest
```

## Operational Notes

### Queues are required

This app uses queued jobs for show parsing, AI mapping, and notifications. In production, the queue worker is not optional.

### AI is optional

If Ollama is unavailable:

- the rest of the app still works
- AI-assisted show mapping and AI assistant features will not

### Demo credentials

Current seeders create development-oriented users from the main README. Treat those as bootstrap accounts and rotate passwords immediately on any non-local deployment.

### Storage and uploads

The Docker stack persists `storage/` in a named volume. The VPS path keeps it on disk under the app directory.

## Recommended next improvement

Before a real production rollout, consider splitting `DemoDataSeeder` out of the default production install path so the VPS installer seeds only roles, defaults, and the first admin account.

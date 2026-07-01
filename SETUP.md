# VortexOps — Setup & Operations Guide

Everything you need to get VortexOps running — from a fresh laptop or a fresh VPS.

---

## Quick reference

| What | Command |
|---|---|
| Local dev (one command) | `composer setup && composer dev` |
| Run tests | `php artisan test` |
| Docker (production-like) | `docker compose up -d` |
| VPS first-time setup | `sudo bash deploy/vps-first-time.sh` |
| Deploy (automatic) | Push to `main` branch |

---

## Table of contents

1. [Requirements](#1-requirements)
2. [Local development](#2-local-development)
3. [Environment variables reference](#3-environment-variables-reference)
4. [Production deployment (Docker on VPS)](#4-production-deployment-docker-on-vps)
5. [AI models (Ollama)](#5-ai-models-ollama)
6. [GitHub CI/CD secrets](#6-github-cicd-secrets)
7. [Barcode scanners](#7-barcode-scanners)
8. [Whatnot scraper](#8-whatnot-scraper)
9. [Common commands](#9-common-commands)
10. [Default accounts](#10-default-accounts)
11. [Updating the app](#11-updating-the-app)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. Requirements

### Local development

| Requirement | Version | Notes |
|---|---|---|
| PHP | 8.3+ | With extensions: bcmath, exif, gd, intl, pcntl, pdo_mysql, pdo_sqlite, zip, redis |
| Composer | 2.x | `brew install composer` or https://getcomposer.org |
| Node.js | 22.x | `brew install node` or https://nodejs.org |
| MySQL | 8.4 (or SQLite for dev) | SQLite works out of the box — no install needed |
| Redis | 7.x | `brew install redis` — needed for cache + session in production |
| Ollama | Latest | https://ollama.com — needed only for AI features |

### Production (Docker)

| Requirement | Notes |
|---|---|
| Ubuntu 22.04 or 24.04 VPS | 8 GB RAM minimum (16 GB recommended for 7B AI models) |
| Docker 24+ | Installed by `deploy/vps-first-time.sh` |
| Docker Compose v2 | Included with modern Docker |
| Open ports | 80 (HTTP), 443 (HTTPS), 22 (SSH) |

---

## 2. Local development

### One-command setup

```bash
git clone git@github.com:damell01/VortexOps.git
cd VortexOps
composer setup
```

`composer setup` runs: `composer install` → copy `.env` → generate app key → migrate → `npm install` → `npm run build`.

### Start everything

```bash
composer dev
```

This starts 4 processes concurrently (requires the `concurrently` npm package, installed by `npm install`):

| Process | What it does |
|---|---|
| `php artisan serve` | App at http://localhost:8000 |
| `php artisan queue:listen --tries=1` | Queue worker (AI jobs, notifications) |
| `php artisan pail` | Real-time log tail |
| `npm run dev` | Vite hot-reload |

### Manual setup (step by step)

```bash
# 1. Clone
git clone git@github.com:damell01/VortexOps.git && cd VortexOps

# 2. PHP dependencies
composer install

# 3. Environment
cp .env.example .env
php artisan key:generate

# 4. Database (SQLite by default — no MySQL needed for dev)
php artisan migrate --seed

# 5. Frontend assets
npm install
npm run build          # or: npm run dev (for hot-reload)

# 6. Start the app
php artisan serve

# 7. Queue worker (separate terminal — needed for AI jobs and notifications)
php artisan queue:work --sleep=3 --tries=3 --timeout=120

# 8. Scheduler (optional — runs cron jobs locally)
php artisan schedule:work
```

Open http://localhost:8000/admin

---

## 3. Environment variables reference

### Core app

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `VortexOps` | App name shown in UI |
| `APP_KEY` | *(generated)* | Laravel encryption key — generate with `php artisan key:generate` |
| `APP_URL` | `http://localhost` | Full URL including scheme — used in emails and redirects |
| `APP_OWNER_EMAIL` | `dbellcreations@gmail.com` | Email for owner-only features (module toggles, balance widgets) |
| `APP_ENV` | `local` | `local` for dev, `production` for VPS |
| `APP_DEBUG` | `true` | Set to `false` in production |

### Database

| Variable | Default | Description |
|---|---|---|
| `DB_CONNECTION` | `sqlite` (dev) / `mysql` (prod) | Driver |
| `DB_HOST` | `mysql` | MySQL host — `mysql` for Docker, `127.0.0.1` for bare-metal |
| `DB_PORT` | `3306` | MySQL port |
| `DB_DATABASE` | `vortexops` | Database name |
| `DB_USERNAME` | `vortexops` | Database user |
| `DB_PASSWORD` | `change-me` | **Change this in production** |
| `DB_ROOT_PASSWORD` | `rootpass` | MySQL root password (Docker only) — **Change this** |

### Queue, cache, sessions

| Variable | Default | Description |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | Always use `database` — jobs stored in MySQL |
| `CACHE_STORE` | `redis` (prod) / `database` (dev) | Redis strongly recommended in production |
| `SESSION_DRIVER` | `redis` (prod) / `database` (dev) | Redis strongly recommended in production |
| `REDIS_HOST` | `redis` (Docker) / `127.0.0.1` (local) | Redis server |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | *(empty)* | Redis password if set |

### AI (Ollama)

| Variable | Default | Description |
|---|---|---|
| `OLLAMA_BASE_URL` | `http://ollama:11434` (Docker) | Ollama server URL |
| `OLLAMA_MODEL` | `llama3.2:3b` | Text model for AI chat and show mapping |
| `OLLAMA_VISION_MODEL` | `moondream` | Vision model for reading packing slip photos/PDFs |
| `OLLAMA_TIMEOUT` | `60` | HTTP timeout in seconds for Ollama requests |

> **Model guide:**
> - `llama3.2:3b` — 2 GB — text AI chat + show mapping. Works on 8 GB RAM.
> - `moondream` — 1.5 GB — reads packing slip photos (required for manifest import).
> - `llava:7b` — 4.5 GB — higher-quality packing slip reading. Needs 8–16 GB free RAM.
> - Both can run simultaneously on 8 GB RAM (`llama3.2:3b` + `moondream` ≈ 3.5 GB).

### Whatnot scraper

| Variable | Default | Description |
|---|---|---|
| `WHATNOT_EMAIL` | *(required)* | Seller account email |
| `WHATNOT_PASSWORD` | *(required)* | Seller account password |
| `WHATNOT_IMPORT_LIMIT` | `50` | Max shows to fetch per run |
| `SCRAPER_API_TOKEN` | *(required)* | Secret token for the scraper webhook endpoint — any random string |

### Mail

| Variable | Default | Description |
|---|---|---|
| `MAIL_MAILER` | `log` | `log` (dev), `smtp`, `postmark`, `resend`, or `ses` |
| `MAIL_HOST` | `127.0.0.1` | SMTP host |
| `MAIL_PORT` | `2525` | SMTP port |
| `MAIL_USERNAME` | *(empty)* | SMTP username |
| `MAIL_PASSWORD` | *(empty)* | SMTP password |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | Sender address |
| `MAIL_FROM_NAME` | `VortexOps` | Sender name |

### Docker-only

| Variable | Default | Description |
|---|---|---|
| `RUN_MIGRATIONS` | `true` (app) / `false` (workers) | Auto-run `migrate --force` on container start |
| `WAIT_FOR_DB` | `true` | Wait for MySQL health before starting |

---

## 4. Production deployment (Docker on VPS)

### First-time VPS setup

SSH into your VPS, then run the setup script:

```bash
# Download and run (as root or sudo user)
curl -fsSL https://raw.githubusercontent.com/damell01/VortexOps/main/deploy/vps-first-time.sh | sudo bash

# — OR — clone the repo and run locally:
sudo bash deploy/vps-first-time.sh
```

The script:
1. Installs Docker + Docker Compose
2. Creates `/opt/vortexops/`
3. Creates `.env.docker` from template (you fill in your values)
4. Configures the firewall (UFW)
5. Pulls the Docker image and starts all services
6. Runs migrations and seeds default data

### Manual first-time setup

```bash
# 1. Install Docker
curl -fsSL https://get.docker.com | sh
usermod -aG docker $USER   # log out and back in after this

# 2. Create app directory
mkdir -p /opt/vortexops
cd /opt/vortexops

# 3. Create .env.docker (copy from repo and edit)
cp /path/to/repo/.env.docker.example .env.docker
nano .env.docker
# Required changes:
#   APP_KEY         — see below
#   APP_URL         — https://yourdomain.com
#   APP_OWNER_EMAIL — your email
#   DB_PASSWORD     — strong password
#   DB_ROOT_PASSWORD — strong password (different)
#   SCRAPER_API_TOKEN — any random string

# 4. Generate APP_KEY
docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));"
# paste the output into .env.docker as APP_KEY=

# 5. docker-compose.yml is pushed by CI/CD automatically after first deploy.
# For manual first-time start, copy it here:
cp /path/to/repo/docker-compose.yml .

# 6. Start services
docker compose up -d

# 7. Wait ~30s for MySQL, then migrate and seed
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=DefaultDataSeeder --force
docker compose exec app php artisan db:seed --class=SuperAdminSeeder --force

# 8. Warm caches
docker compose exec app php artisan optimize
docker compose exec app php artisan filament:optimize
```

### SSL with Nginx reverse proxy

The app container listens on `127.0.0.1:8080`. Put Nginx in front for SSL:

```bash
apt-get install -y nginx certbot python3-certbot-nginx

# Replace example.com with your actual domain
certbot --nginx -d yourdomain.com
```

Nginx config (`/etc/nginx/sites-available/vortexops`):

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    client_max_body_size 25M;   # allow packing slip photo uploads

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/vortexops /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

---

## 5. AI models (Ollama)

VortexOps uses Ollama to run AI locally — no data sent to external APIs.

### Start Ollama (Docker)

Ollama is an optional Docker profile. Enable it once and it stays running:

```bash
cd /opt/vortexops
docker compose --profile ai up -d ollama
```

### Pull models (one-time per model)

```bash
# Text model — AI chat + show mapping
docker compose exec ollama ollama pull llama3.2:3b

# Vision model — reads packing slip photos and PDFs
docker compose exec ollama ollama pull moondream

# Optional: higher-quality vision (needs ~4.5 GB free RAM, ~9 GB total with text model)
docker compose exec ollama ollama pull llava:7b
```

### Check which models are installed

```bash
docker compose exec ollama ollama list
```

### Test Ollama is working

```bash
# From inside the app container:
docker compose exec app curl -s http://ollama:11434/api/tags | python3 -m json.tool
```

Or go to **Settings → AI Assistant** in the app and click "Test Ollama".

### Local dev (without Docker)

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Start it
ollama serve

# Pull models
ollama pull llama3.2:3b
ollama pull moondream
```

Then set in `.env`:
```
OLLAMA_BASE_URL=http://localhost:11434
```

### RAM requirements

| Setup | RAM needed |
|---|---|
| App stack only (no AI) | ~700 MB |
| + llama3.2:3b (text) | +2 GB → ~2.7 GB total |
| + moondream (vision) | +1.5 GB → ~4.2 GB total |
| + llava:7b (vision, better quality) | +4.5 GB → ~7.2 GB total |

**8 GB VPS**: run `llama3.2:3b` + `moondream` comfortably.  
**16 GB VPS**: run `llama3.2:3b` + `llava:7b` for best quality.

---

## 6. GitHub CI/CD secrets

Add these in **GitHub → Settings → Secrets and variables → Actions**:

| Secret | What it is | How to get it |
|---|---|---|
| `GHCR_PAT` | GitHub Personal Access Token | GitHub → Settings → Developer settings → PATs (classic) → New. Scopes: `write:packages`, `read:packages`, `delete:packages` |
| `VPS_HOST` | VPS IP address or hostname | Your Hostinger VPS IP |
| `VPS_USER` | SSH username | Usually `root` on Hostinger |
| `VPS_SSH_KEY` | Private SSH key | Run `ssh-keygen -t ed25519 -C "vortexops-deploy"`, then copy the private key content. Add the public key to `~/.ssh/authorized_keys` on the VPS. |

### How CI/CD works

| Event | What happens |
|---|---|
| Push to any branch | PHPUnit tests run (SQLite in-memory, ~2 sec) |
| Push to `main` | Tests run → Docker image built and pushed to GHCR → `docker-compose.yml` copied to VPS → `docker compose pull && up -d` → migrations run → caches warmed |
| Manual dispatch | Same as push to `main` |

No manual deploys needed after this is set up — just push to `main`.

---

## 7. Barcode scanners

The app works with any scanner that acts as a keyboard (all common scanners do).

### How it works

Scanners type the barcode number and press Enter. The Receive Pallet page and Inventory Scanner page both have an autofocus input that captures this automatically — no drivers or special setup needed.

### Recommended hardware

| Type | Model | Price range | Notes |
|---|---|---|---|
| USB wired (basic) | Inateck BCST-70, Tera 1D/2D | $25–$50 | Plug-and-play on any computer |
| Bluetooth (desk use) | Honeywell Voyager 1602g | $80–$120 | Pairs to phone/tablet/PC |
| Bluetooth ring | Tera Ring Scanner | $50–$80 | Hands-free for warehouse use |
| Android mobile computer | Zebra TC21, Honeywell CT45 | $400–$600 | Full Android + built-in scanner — best warehouse option |
| Phone camera | Built-in (Chrome/Android/Edge) | Free | Tap the camera icon on the scanner pages — uses BarcodeDetector Web API |

### Phone camera scanning

Tap the camera icon on the **Inventory Scanner** or **Receive Pallet** pages. Works in:
- Chrome on Android ✓
- Chrome on desktop (Windows/Mac) ✓  
- Edge on Windows ✓
- Safari on iPhone ✗ (not supported — use a Bluetooth scanner instead)

---

## 8. Whatnot scraper

The Whatnot scraper imports your show history from the Whatnot seller dashboard using Playwright (browser automation).

### Requirements

The scraper runs as a Node.js script that needs Playwright + Chromium. In Docker, this requires adding them to the image or running the scraper outside Docker.

**Option A — run scraper from host machine (simplest):**

```bash
# On your local machine or VPS (outside Docker):
npm install -g playwright
npx playwright install chromium

# Set in .env:
WHATNOT_EMAIL=your@email.com
WHATNOT_PASSWORD=yourpassword

# Run manually:
php artisan whatnot:import
```

**Option B — scheduled import via artisan command:**

```bash
# Import last 50 shows:
php artisan whatnot:import

# Import specific limit:
php artisan whatnot:import --limit=100

# Debug mode (saves screenshots to /tmp):
php artisan whatnot:import --debug
```

The scheduler runs `whatnot:import` automatically. Make sure the scheduler service is running (it is, via Docker's `scheduler` service).

### Exit codes

| Code | Meaning |
|---|---|
| 0 | Success — JSON output on stdout |
| 1 | Login failed or navigation error |
| 2 | Selector miss — Whatnot UI changed, update `SELECTORS` in `scripts/whatnot-scraper.cjs` |

---

## 9. Common commands

### Tests

```bash
# Run all tests (fast — uses SQLite in-memory)
php artisan test

# Run a specific test file or filter
php artisan test --filter ReceivingService
php artisan test tests/Feature/PayoutServiceTest.php

# Run in parallel
php artisan test --parallel
```

### Queue

```bash
# Start the main worker
php artisan queue:work --sleep=3 --tries=3 --timeout=120

# Start the AI worker (separate — never blocks the main queue)
php artisan queue:work --queue=ai --sleep=5 --tries=1 --timeout=300

# Check failed jobs
php artisan queue:failed

# Retry a failed job
php artisan queue:retry <id>

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Monitor queues in real-time
php artisan queue:monitor default:10,ai:5
```

### Cache and optimization

```bash
# Clear everything
php artisan optimize:clear

# Rebuild all caches (do this after deploy)
php artisan optimize
php artisan filament:optimize

# Clear just the config/route/view caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Database

```bash
# Run new migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Fresh database + seed (DESTROYS all data)
php artisan migrate:fresh --seed

# Just seed (without wiping)
php artisan db:seed --class=DefaultDataSeeder

# Backup database (runs daily at 2am via scheduler)
php artisan db:backup
```

### Maintenance

```bash
# Enable maintenance mode
php artisan down --retry=60 --message="Updating VortexOps…"

# Disable maintenance mode
php artisan up

# View live logs
php artisan pail

# Tail specific log file
tail -f storage/logs/laravel.log
```

### Docker commands

```bash
cd /opt/vortexops

# View running containers
docker compose ps

# View logs (all services)
docker compose logs -f

# View logs (specific service)
docker compose logs -f app
docker compose logs -f ai-worker

# Restart a service
docker compose restart app
docker compose restart ai-worker

# Shell into the app container
docker compose exec app bash

# Run artisan inside Docker
docker compose exec app php artisan migrate
docker compose exec app php artisan queue:failed

# Pull latest image and redeploy (also done automatically by CI/CD)
docker compose pull
docker compose up -d --remove-orphans
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose exec app php artisan filament:optimize
```

---

## 10. Default accounts

Created by `php artisan db:seed` (or `DefaultDataSeeder` + `SuperAdminSeeder`):

| Role | Email | Password | Access |
|---|---|---|---|
| Admin | `admin@vortexbreaks.com` | `password` | Full admin access |
| Super Admin (dev) | `dev@vortexbreaks.com` | `devpassword` | Everything + role assignment |

> **Change these passwords immediately** in production via Settings → Users, or:
> ```bash
> php artisan tinker
> # In tinker:
> App\Models\User::where('email', 'admin@vortexbreaks.com')->first()->update(['password' => bcrypt('your-new-password')]);
> ```

Demo data created by seeder:
- 3 streamers with different payout types
- 8 inventory items with stock across all locations
- 3 shows at different workflow stages (reconciled / pending approval / draft)
- Deduction requests, payouts, and 2 weekly pay run batches

---

## 11. Updating the app

### Via GitHub (normal workflow)

Just push to `main`:

```bash
git push origin main
```

CI/CD handles everything: tests → build → push image → deploy to VPS → migrate → optimize.

Takes ~3–4 minutes from push to live.

### Manual update on VPS

```bash
cd /opt/vortexops

# Pull new image
docker compose pull

# Restart with new image (zero-downtime: workers restart first)
docker compose up -d --remove-orphans

# Run any new migrations
docker compose exec app php artisan migrate --force

# Rebuild caches
docker compose exec app php artisan optimize
docker compose exec app php artisan filament:optimize
```

---

## 12. Troubleshooting

### App won't start / white screen

```bash
# Check logs
docker compose logs app
# or locally:
tail -f storage/logs/laravel.log
```

Common causes:
- `APP_KEY` not set — run `php artisan key:generate`
- Missing `.env` file — run `cp .env.example .env`
- Storage not writable — run `chmod -R 775 storage bootstrap/cache`

### Queue jobs not running

```bash
# Check queue worker is up
docker compose ps worker

# Check for failed jobs
docker compose exec app php artisan queue:failed

# Restart worker
docker compose restart worker
docker compose restart ai-worker
```

### Ollama not working

```bash
# Is Ollama running?
docker compose ps ollama

# Check Ollama is reachable from the app container
docker compose exec app curl -s http://ollama:11434/api/tags

# Is the model pulled?
docker compose exec ollama ollama list

# Pull missing model
docker compose exec ollama ollama pull llama3.2:3b
docker compose exec ollama ollama pull moondream
```

### Packing slip AI not working

The vision model must be pulled separately from the text model:

```bash
docker compose exec ollama ollama pull moondream
```

If Imagick isn't available for PDF → image conversion, the app falls back to `pdftoppm` (poppler-utils), which is installed in the Docker image. If you see "PDF conversion requires Imagick or poppler-utils", rebuild the Docker image with the latest Dockerfile.

### Migrations fail

```bash
# Check which migrations have run
docker compose exec app php artisan migrate:status

# Force run (skips safety checks)
docker compose exec app php artisan migrate --force
```

### Barcode scanner not working

- Make sure the barcode input field on the page has focus (click it once)
- Test: type anything and press Enter — same action as a scanner
- On mobile: tap the input field to focus, then scan
- USB scanner: plug in, no setup needed
- Bluetooth scanner: pair in OS settings first, then it works as a keyboard

### Storage link missing (images not displaying)

```bash
php artisan storage:link
# or in Docker:
docker compose exec app php artisan storage:link
```

---

*Last updated: 2026-07-01*

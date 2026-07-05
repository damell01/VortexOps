# VortexOps — Setup & Operations Guide

Everything you need: local dev, production VPS from scratch, CI/CD, SSL, AI, and ongoing operations.

---

## Quick reference

| What | Command |
|---|---|
| Local dev (one command) | `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && php artisan serve` |
| Run tests | `php artisan test` |
| VPS first-time setup | `sudo bash deploy/vps-first-time.sh` |
| Nginx + SSL setup | `sudo bash deploy/vps-setup.sh` |
| Deploy (automatic after setup) | Push to `main` branch |
| Pull latest on VPS manually | `cd /opt/vortexops && docker compose pull && docker compose up -d --remove-orphans` |

---

## Table of contents

1. [Local development](#1-local-development)
2. [Production VPS setup — full walkthrough](#2-production-vps-setup--full-walkthrough)
3. [GitHub CI/CD secrets](#3-github-cicd-secrets)
4. [SSL with Nginx](#4-ssl-with-nginx)
5. [AI models (Ollama)](#5-ai-models-ollama)
6. [Whatnot scraper](#6-whatnot-scraper)
7. [Environment variables reference](#7-environment-variables-reference)
8. [Common commands](#8-common-commands)
9. [Default accounts](#9-default-accounts)
10. [Updating the app](#10-updating-the-app)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. Local development

### Requirements

| Requirement | Version |
|---|---|
| PHP | 8.3+ with bcmath, exif, gd, intl, pcntl, pdo_mysql, pdo_sqlite, zip, redis |
| Composer | 2.x |
| Node.js | 22.x |
| MySQL | 8.4 (or SQLite — works out of the box for dev) |
| Redis | 7.x (for cache/session; optional for dev) |

### Setup

```bash
git clone git@github.com:damell01/VortexOps.git
cd VortexOps

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed    # uses SQLite by default — no MySQL needed

npm install
npm run build                 # or: npm run dev (Vite hot-reload)
```

### Start the app

```bash
# Terminal 1 — app server
php artisan serve

# Terminal 2 — queue worker (needed for AI jobs and notifications)
php artisan queue:work --sleep=3 --tries=3 --timeout=120

# Terminal 3 — optional: log tail
php artisan pail
```

Open http://localhost:8000/admin

Default credentials: `admin@vortexbreaks.com` / `password`

---

## 2. Production VPS setup — full walkthrough

### What you need before starting

- A VPS running **Ubuntu 22.04 or 24.04** (minimum 4 GB RAM; 8 GB recommended for AI models)
- A domain name with an **A record pointed to your VPS IP**
- SSH access to the VPS as root or a sudo user
- This repository on GitHub (already the case)

### Overview of the full setup flow

```
① SSH into VPS
② Run vps-first-time.sh  →  Docker + app + database + migrations
③ Run vps-setup.sh       →  Nginx + SSL certificate
④ Add GitHub Secrets     →  Enables auto-deploy on every push to main
⑤ Push to main           →  First CI/CD build → live update
```

---

### Step 1 — SSH into your VPS

```bash
ssh root@YOUR_VPS_IP
```

---

### Step 2 — Run the first-time setup script

This script handles: Docker install, `/opt/vortexops/` directory, `.env.docker` file, firewall, image pull, and first migrations.

**Option A — clone the repo on the VPS (recommended for first-time):**

```bash
git clone https://github.com/damell01/VortexOps.git /tmp/vortexops
sudo bash /tmp/vortexops/deploy/vps-first-time.sh
rm -rf /tmp/vortexops   # clean up — the VPS only needs /opt/vortexops after this
```

**Option B — download the script directly (if the repo is already public on main):**

```bash
curl -fsSL https://raw.githubusercontent.com/damell01/VortexOps/main/deploy/vps-first-time.sh \
    | sudo bash
```

**What the script does:**
1. Installs Docker Engine + Docker Compose
2. Creates `/opt/vortexops/`
3. Creates `/opt/vortexops/.env.docker` from a template and pauses so you can fill it in
4. Configures the UFW firewall (allows SSH, HTTP, HTTPS)
5. Generates `APP_KEY` automatically if you left it blank
6. Pulls the Docker image from GHCR and starts all services
7. Runs `php artisan migrate --force` and seeds default data
8. Warms all caches

**During the script, edit `.env.docker` when prompted:**

```bash
nano /opt/vortexops/.env.docker
```

Required values to change:

| Variable | What to set |
|---|---|
| `APP_KEY` | Leave blank — the script generates it automatically |
| `APP_URL` | `https://yourdomain.com` |
| `APP_OWNER_EMAIL` | Your email (owner-only features gate on this) |
| `DB_PASSWORD` | A strong random password |
| `DB_ROOT_PASSWORD` | A different strong random password |
| `WHATNOT_EMAIL` | Your Whatnot seller account email |
| `WHATNOT_PASSWORD` | Your Whatnot account password |
| `SCRAPER_API_TOKEN` | Any random string — used for internal API auth |

Everything else has sensible defaults.

**After the script finishes**, the app is running at `http://YOUR_VPS_IP:8080` — not HTTPS yet. That's next.

---

### Step 3 — Nginx + SSL

Run the interactive SSL setup script. It asks for your domain, email, and deploy user, then installs Nginx and gets a Let's Encrypt certificate:

```bash
# Still on the VPS as root:
sudo bash /tmp/vortexops/deploy/vps-setup.sh
# (or clone the repo again if you deleted /tmp/vortexops)
```

It will prompt:
- Your domain (e.g. `ops.vortexbreaks.com`)
- Email for Let's Encrypt expiry alerts
- Deploy user (usually `ubuntu` or `root`)

After it completes, the app is live at **`https://yourdomain.com`**.

Then update `.env.docker` with the HTTPS URL and restart:

```bash
sed -i 's|APP_URL=.*|APP_URL=https://yourdomain.com|' /opt/vortexops/.env.docker
cd /opt/vortexops
docker compose restart app worker scheduler ai-worker
docker compose exec app php artisan optimize
```

> **Manual SSL setup?** See `deploy/nginx-ssl.md` for a detailed step-by-step without the script.

---

### Step 4 — GitHub Secrets (enables auto-deploy)

Add these in **GitHub → your repo → Settings → Secrets and variables → Actions**:

| Secret | Value | How to get it |
|---|---|---|
| `GHCR_PAT` | GitHub Personal Access Token | GitHub → Settings → Developer settings → Personal access tokens (classic) → New. Enable scopes: `write:packages`, `read:packages`, `delete:packages` |
| `VPS_HOST` | Your VPS IP or domain | From your VPS provider |
| `VPS_USER` | SSH username | `root` on most fresh VPS setups |
| `VPS_SSH_KEY` | Private SSH key content | See below |

**Generating an SSH deploy key:**

```bash
# On your local machine:
ssh-keygen -t ed25519 -C "vortexops-deploy" -f ~/.ssh/vortexops_deploy

# Add the public key to your VPS:
ssh-copy-id -i ~/.ssh/vortexops_deploy.pub root@YOUR_VPS_IP

# Copy the private key content into GitHub as VPS_SSH_KEY:
cat ~/.ssh/vortexops_deploy
```

---

### Step 5 — Push to main and verify CI/CD

```bash
# On your local machine:
git push origin main
```

Go to **GitHub → Actions** and watch the deploy run. It takes 3–5 minutes. When it's green:

1. Visit `https://yourdomain.com/admin` — app should be live
2. Log in with `admin@vortexbreaks.com` / `password`
3. **Immediately change the default passwords** (Settings → Users)

From this point on, every push to `main` automatically builds, pushes the Docker image to GHCR, and redeploys to your VPS.

---

### What lives where on the VPS

```
/opt/vortexops/
├── docker-compose.yml    ← pushed here automatically by CI/CD on every deploy
├── .env.docker           ← created once manually; never overwritten by CI/CD
```

The Docker image comes from `ghcr.io/damell01/vortexops:latest` (GHCR). The VPS never needs the source code.

---

## 3. GitHub CI/CD secrets

See [Step 4 above](#step-4--github-secrets-enables-auto-deploy) for the setup steps.

### How CI/CD works

| Event | What happens |
|---|---|
| Push to any branch | PHPUnit tests run (SQLite in-memory, ~30 sec) |
| Push to `main` | Tests → Docker image built → pushed to GHCR → `docker-compose.yml` copied to VPS → `docker compose pull && up -d` → `migrate --force` → caches warmed |
| Manual dispatch | Same as push to `main` (trigger from GitHub Actions tab) |

---

## 4. SSL with Nginx

The automated path is `deploy/vps-setup.sh` (see Step 3 above). For manual control, see `deploy/nginx-ssl.md`.

### SSL auto-renewal

Let's Encrypt certs expire after 90 days. Certbot installs a systemd timer that renews automatically every 60 days.

```bash
# Confirm the renewal timer is active:
sudo systemctl status certbot.timer

# Test a dry-run renewal:
sudo certbot renew --dry-run
```

### Check the Nginx config

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 5. AI models (Ollama)

VortexOps uses Ollama to run AI locally — no data sent to external APIs. Ollama is **optional**; the rest of the app works without it.

### Enable Ollama (Docker)

```bash
cd /opt/vortexops
docker compose --profile ai up -d ollama
```

The Ollama container auto-pulls both models on first start (configured in `docker-compose.yml`). You can also pull manually:

```bash
# Text model — AI chat + show title parsing + inventory mapping
docker compose exec ollama ollama pull llama3.2:3b

# Vision model — reads packing slip photos and PDFs
docker compose exec ollama ollama pull moondream
```

### Check what's installed

```bash
docker compose exec ollama ollama list
```

### Test Ollama is reachable from the app

```bash
docker compose exec app curl -s http://ollama:11434/api/tags
```

Or go to **Settings → AI Assistant** in the app and click "Test Ollama".

### RAM requirements

| Setup | RAM needed |
|---|---|
| App stack only (no AI) | ~700 MB |
| + `llama3.2:3b` (text AI) | +2 GB → ~2.7 GB total |
| + `moondream` (vision, packing slips) | +1.5 GB → ~4.2 GB total |
| + `llava:7b` (better vision quality) | +4.5 GB → ~7.2 GB total |

**8 GB VPS**: run `llama3.2:3b` + `moondream` comfortably.
**16 GB VPS**: run `llama3.2:3b` + `llava:7b` for best packing-slip quality.

### Disable Ollama

Just don't use `--profile ai`. The `ai-worker` service always runs but simply won't process AI jobs if Ollama isn't available — no errors in the main app.

---

## 6. Whatnot scraper

The Whatnot scraper imports your show history from the Whatnot seller dashboard using Playwright (browser automation). It runs as a Node.js script **inside the app container** — Node.js and Chromium are baked into the Docker image.

### Configuration

Set these in `/opt/vortexops/.env.docker`:

```env
WHATNOT_EMAIL=your-seller@email.com
WHATNOT_PASSWORD=yourpassword
WHATNOT_LIMIT=50
```

### Running the scraper

```bash
# Import the last 50 shows (or WHATNOT_LIMIT):
docker compose exec app php artisan whatnot:import

# Import more:
docker compose exec app php artisan whatnot:import --limit=100

# Debug mode (saves screenshots to /tmp for troubleshooting):
docker compose exec app php artisan whatnot:import --debug
```

The scheduler runs `whatnot:import` automatically via the `scheduler` Docker service.

### Exit codes

| Code | Meaning | Fix |
|---|---|---|
| 0 | Success | — |
| 1 | Login failed or navigation error | Check `WHATNOT_EMAIL` / `WHATNOT_PASSWORD` |
| 2 | Selector miss — Whatnot UI changed | Update `SELECTORS` object at the top of `scripts/whatnot-scraper.cjs` |

---

## 7. Environment variables reference

### Core app

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `VortexOps` | App name shown in UI |
| `APP_KEY` | *(generated)* | Laravel encryption key |
| `APP_URL` | `http://localhost` | Full URL including scheme — used in emails and redirects |
| `APP_OWNER_EMAIL` | `dbellcreations@gmail.com` | Owner email — gates module toggles and balance widgets |
| `APP_ENV` | `local` | `local` for dev, `production` for VPS |
| `APP_DEBUG` | `true` | Always `false` in production |
| `APP_IMAGE` | `vortexops:local` | Docker image to use — set to `ghcr.io/damell01/vortexops:latest` on VPS |

### Database

| Variable | Default | Description |
|---|---|---|
| `DB_CONNECTION` | `sqlite` (dev) / `mysql` (prod) | Driver |
| `DB_HOST` | `mysql` | MySQL host — `mysql` for Docker, `127.0.0.1` for bare-metal |
| `DB_PORT` | `3306` | MySQL port |
| `DB_DATABASE` | `vortexops` | Database name |
| `DB_USERNAME` | `vortexops` | Database user |
| `DB_PASSWORD` | *(required)* | **Change this** — strong random password |
| `DB_ROOT_PASSWORD` | *(required)* | MySQL root password (Docker only) — **different from DB_PASSWORD** |

### Queue, cache, sessions

| Variable | Default | Description |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | Always `database` — jobs stored in MySQL |
| `CACHE_STORE` | `redis` | Redis required in production |
| `SESSION_DRIVER` | `redis` | Redis required in production |
| `REDIS_HOST` | `redis` (Docker) | Redis server hostname |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | *(empty)* | Redis password if set |

### AI (Ollama)

| Variable | Default | Description |
|---|---|---|
| `OLLAMA_BASE_URL` | `http://ollama:11434` | Ollama server URL |
| `OLLAMA_MODEL` | `llama3.2:3b` | Text model for AI chat and show mapping |
| `OLLAMA_VISION_MODEL` | `moondream` | Vision model for packing slip photos/PDFs |
| `OLLAMA_TIMEOUT` | `60` | HTTP timeout in seconds |

### Whatnot scraper

| Variable | Default | Description |
|---|---|---|
| `WHATNOT_EMAIL` | *(required)* | Seller account email |
| `WHATNOT_PASSWORD` | *(required)* | Seller account password |
| `WHATNOT_LIMIT` | `50` | Max shows to fetch per run |
| `SCRAPER_API_TOKEN` | *(required)* | Secret token for scraper webhook — any random string |

### Mail

| Variable | Default | Description |
|---|---|---|
| `MAIL_MAILER` | `log` | `log` for dev; `smtp`, `postmark`, or `ses` for production |
| `MAIL_HOST` | `127.0.0.1` | SMTP host |
| `MAIL_PORT` | `2525` | SMTP port |
| `MAIL_USERNAME` | *(empty)* | SMTP username |
| `MAIL_PASSWORD` | *(empty)* | SMTP password |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | Sender address |

### Docker-only

| Variable | Default | Description |
|---|---|---|
| `RUN_MIGRATIONS` | `true` (app) / `false` (workers) | Auto-run `migrate --force` on container start |
| `WAIT_FOR_DB` | `true` | Wait for MySQL to be healthy before starting |

---

## 8. Common commands

### Docker (from `/opt/vortexops`)

```bash
# View all running containers and their health
docker compose ps

# View logs (all services)
docker compose logs -f

# View logs for a specific service
docker compose logs -f app
docker compose logs -f ai-worker

# Shell into the app container
docker compose exec app bash

# Run artisan commands inside Docker
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
docker compose exec app php artisan queue:failed

# Restart a specific service
docker compose restart app
docker compose restart worker ai-worker

# Pull latest image and redeploy
docker compose pull
docker compose up -d --remove-orphans
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose exec app php artisan filament:optimize
```

### Queue

```bash
# Check failed jobs
docker compose exec app php artisan queue:failed

# Retry a specific failed job
docker compose exec app php artisan queue:retry <id>

# Retry all failed jobs
docker compose exec app php artisan queue:retry all

# Clear all failed jobs
docker compose exec app php artisan queue:flush
```

### Cache

```bash
# Clear all caches
docker compose exec app php artisan optimize:clear

# Rebuild all caches (after deploy)
docker compose exec app php artisan optimize
docker compose exec app php artisan filament:optimize
```

### Database

```bash
# Run new migrations
docker compose exec app php artisan migrate --force

# Check migration status
docker compose exec app php artisan migrate:status

# Manual DB backup
docker compose exec app php artisan db:backup
```

### Tests (local)

```bash
# Run all tests — uses SQLite in-memory, very fast
php artisan test

# Filter to a specific test
php artisan test --filter ReceivingService

# Run in parallel
php artisan test --parallel
```

---

## 9. Default accounts

Created by `php artisan db:seed` (seeders: `DefaultDataSeeder` + `SuperAdminSeeder`):

| Role | Email | Password | Access |
|---|---|---|---|
| Admin | `admin@vortexbreaks.com` | `password` | Full admin access |
| Super Admin | `dev@vortexbreaks.com` | `devpassword` | Everything + role assignment |

> **Change these passwords immediately in production.** Via Settings → Users, or:
> ```bash
> docker compose exec app php artisan tinker
> # In tinker:
> App\Models\User::where('email', 'admin@vortexbreaks.com')
>     ->first()
>     ->update(['password' => bcrypt('your-new-password')]);
> ```

Demo data from seeder:
- 3 streamers with different payout types
- 8 inventory items with stock across all locations
- 3 shows at different workflow stages (reconciled / pending approval / draft)
- Deduction requests, payouts, and 2 weekly pay run batches

---

## 10. Updating the app

### Normal workflow (after CI/CD is set up)

Just push to `main`:

```bash
git push origin main
```

CI/CD handles everything: tests → build image → push to GHCR → update VPS → migrate → optimize. Takes 3–5 minutes.

### Manual update on VPS

```bash
cd /opt/vortexops
docker compose pull
docker compose up -d --remove-orphans
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
docker compose exec app php artisan filament:optimize
```

### Maintenance mode

```bash
# Put app into maintenance mode (visitors see a friendly message)
docker compose exec app php artisan down --retry=60

# Bring it back up
docker compose exec app php artisan up
```

---

## 11. Troubleshooting

### App won't start / shows a 500 error

```bash
docker compose logs app
docker compose exec app php artisan about
```

Common causes:
- `APP_KEY` not set — the setup script generates it; if blank, run: `docker compose exec app php artisan key:generate --show` and add the result to `.env.docker`, then restart
- `.env.docker` missing or incorrect — check `/opt/vortexops/.env.docker` exists
- Storage not writable — entrypoint handles this; if needed: `docker compose exec app chmod -R 775 storage bootstrap/cache`

### 502 Bad Gateway

The Nginx proxy can't reach the app container on port 8080.

```bash
# Is the app container running and healthy?
docker compose ps

# Is it listening on 8080?
curl -I http://127.0.0.1:8080/health
```

If not healthy, check: `docker compose logs app`

### Queue jobs not running

```bash
# Is the worker up?
docker compose ps worker

# Are there failed jobs?
docker compose exec app php artisan queue:failed

# Restart workers
docker compose restart worker ai-worker
```

### Ollama not working

```bash
# Is Ollama running?
docker compose ps ollama

# Test it from the app container
docker compose exec app curl -s http://ollama:11434/api/tags

# Are the models pulled?
docker compose exec ollama ollama list

# Pull missing models
docker compose exec ollama ollama pull llama3.2:3b
docker compose exec ollama ollama pull moondream
```

### Packing slip AI not working

The vision model must be pulled separately:

```bash
docker compose exec ollama ollama pull moondream
```

### Whatnot scraper failing

```bash
# Run with debug mode to capture screenshots of where it fails
docker compose exec app php artisan whatnot:import --debug

# Exit code 1 = wrong credentials — check WHATNOT_EMAIL / WHATNOT_PASSWORD in .env.docker
# Exit code 2 = Whatnot UI changed — update SELECTORS in scripts/whatnot-scraper.cjs
```

### Barcode scanner not being picked up

- The input field must have focus — click it once before scanning
- Bluetooth scanners need to be paired in OS settings first; then they act as a keyboard
- Phone camera scanning (Chrome/Android, Edge): tap the camera icon on the scanner or receive-pallet pages

### Storage link missing (uploaded images not displaying)

```bash
docker compose exec app php artisan storage:link
```

The entrypoint script runs this automatically on container start, so this is only needed if something went wrong.

### Migrations fail

```bash
# Check which migrations have and haven't run
docker compose exec app php artisan migrate:status

# Force run
docker compose exec app php artisan migrate --force
```

### SSL certificate not renewing

```bash
sudo systemctl status certbot.timer
sudo certbot renew --dry-run
sudo nginx -t && sudo systemctl reload nginx
```

---

*Last updated: 2026-07-05*

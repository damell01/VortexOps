# VortexOps Quick Commands Reference

## 🔄 Pull & Optimize (Complete Setup)

```bash
# Automated pull + optimize + cache clear (recommended)
bash scripts/optimize.sh

# Or for a specific branch:
bash scripts/optimize.sh claude/whatnot-shipments-scraper-zsuf1m
```

---

## 🧹 Cache Clearing Commands

**Clear all caches at once:**
```bash
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan event:clear
```

**Individual cache clears:**
```bash
# Application cache
php artisan cache:clear

# Config cache
php artisan config:clear

# Routes cache
php artisan route:clear

# Views cache
php artisan view:clear

# Event cache
php artisan event:clear
```

---

## ⚡ Optimize & Build

```bash
# Laravel optimization (caches framework bootstrap, config, routes, views)
php artisan optimize

# Re-build all caches
php artisan cache:clear && php artisan optimize

# Composer optimization (when installing/updating packages)
composer install --optimize-autoloader
composer install --optimize-autoloader --no-dev  # For production
```

---

## 📦 Dependencies & Migrations

```bash
# Install composer dependencies
composer install

# Update composer dependencies
composer update

# Run migrations
php artisan migrate

# Seed database
php artisan migrate --seed

# Refresh database (migrate:fresh + seed)
php artisan migrate:fresh --seed
```

---

## 🚀 Whatnot Scraper Commands

```bash
# Discover shows from /dashboard/lives and scrape shipments
php artisan whatnot:sync-shipments-live

# For a specific channel
php artisan whatnot:sync-shipments-live --channel=vortexshop

# Show command help
php artisan whatnot:sync-shipments-live --help

# List all whatnot commands
php artisan list whatnot
```

### Related Whatnot Commands

```bash
# Full sync (shows → orders → shipments → ledger)
php artisan whatnot:sync-all

# Sync shows and orders
php artisan whatnot:sync

# Refresh shipment status for open orders
php artisan whatnot:sync-shipments

# Import ledger data
php artisan whatnot:import-ledger

# Set up authentication
php artisan whatnot:login

# Clear stuck browser lock
php artisan whatnot:unlock --force
```

---

## 🖥️ Development Server

```bash
# Start dev server (http://localhost:8000)
php artisan serve

# Access Filament Admin
php artisan serve
# Then navigate to: http://localhost:8000/admin

# Start queue worker (for notifications/jobs)
php artisan queue:work

# Start scheduler loop (runs scheduled tasks)
php artisan schedule:run
```

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test class
php artisan test tests/Feature/WhatnotSyncEngineTest.php

# Run tests matching a pattern
php artisan test --filter SyncShipmentsLive

# Verbose output
php artisan test -v
```

---

## 🔐 Database Operations

```bash
# Create database tables
php artisan migrate

# Seed database with demo data
php artisan migrate:fresh --seed

# Check database status
php artisan tinker
# Then: \App\Models\Show::count()

# Backup database
php artisan db:backup
```

---

## 🐛 Debugging & Logs

```bash
# Tail application logs
tail -f storage/logs/laravel.log

# Clear logs
rm storage/logs/*.log

# View queue failures
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## 🌐 Git Operations

```bash
# Check branch status
git status

# Fetch latest changes
git fetch origin

# Pull latest from current branch
git pull

# Pull from specific branch
git pull origin claude/whatnot-shipments-scraper-zsuf1m

# View recent commits
git log --oneline -10

# Switch branches
git checkout develop
git checkout -b new-feature-branch
```

---

## 📊 Monitoring & Maintenance

```bash
# Clear and optimize (full refresh)
bash scripts/optimize.sh

# Check system resources
df -h                    # Disk space
free -h                  # Memory
php -v                   # PHP version
composer show --latest   # Check for outdated packages

# Get full info
php artisan about
```

---

## 🔧 Troubleshooting

### Composer issues
```bash
# Update composer itself
composer self-update

# Clear composer cache
composer clear-cache

# Validate composer.json
composer validate
```

### Browser/Scraper issues
```bash
# Kill stuck Chromium/scraper processes
pkill -9 chromium
pkill -9 whatnot-scraper

# Clear stuck browser lock
php artisan whatnot:unlock --force

# Kill and restart scraper safely
pkill -9 whatnot-scraper chromium && sleep 1 && php artisan whatnot:sync-shipments-live
```

### Permission issues
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage bootstrap/cache

# Make scripts executable
chmod +x scripts/*.sh
```

---

## 📝 Common Workflows

### Deploy a feature branch
```bash
# 1. Pull latest changes and optimize
bash scripts/optimize.sh

# 2. Run tests
php artisan test

# 3. Clear cache before pushing
php artisan optimize

# 4. Push to remote
git push origin feature-branch-name
```

### Quick refresh development environment
```bash
# Everything at once
git pull && composer install --quiet && php artisan cache:clear && php artisan optimize && php artisan migrate:fresh --seed
```

### Schedule periodic scraping
```bash
# Add to crontab (runs every hour)
0 * * * * cd /home/user/VortexOps && php artisan whatnot:sync-shipments-live >> storage/logs/cron.log 2>&1

# Or every 30 minutes
*/30 * * * * cd /home/user/VortexOps && php artisan whatnot:sync-shipments-live >> storage/logs/cron.log 2>&1
```

---

**Last Updated:** 2026-07-31  
**Branch:** `claude/whatnot-shipments-scraper-zsuf1m`

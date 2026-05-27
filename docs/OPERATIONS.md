# Operations Guide

This is the day-to-day admin and maintenance guide for Vortex Breaks / VortexOps.

Use this file for:

- routine code updates
- rebuilding assets
- clearing demo data
- resetting local/dev environments
- quick production sanity checks

For deeper implementation notes, see [TECHNICAL.md](./TECHNICAL.md).  
For deployment setup, see [DEPLOYMENT.md](./DEPLOYMENT.md).  
For a running human-readable update log, see [CHANGELOG.md](./CHANGELOG.md).

---

## Environment notes

- Local/dev commonly uses `sqlite`
- Production uses `mysql`
- Queue driver is `database`
- Session and cache should stay `file` in production unless you intentionally move to Redis

Quick check:

```bash
php artisan about
```

You want production to show:

- `Database .... mysql`
- `Cache ....... file`
- `Session ..... file`

---

## Standard update flow

Run from the project root on the server:

```bash
git pull origin main
composer install --no-dev --prefer-dist --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
```

Then reload services:

```bash
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

If your PHP-FPM service name differs, find it with:

```bash
systemctl list-units --type=service | grep -i php
```

If queues are running:

```bash
php artisan queue:restart
```

---

## Quick production checks

After any update:

```bash
php artisan about
php artisan migrate:status
tail -n 100 storage/logs/laravel.log
```

Basic response-time test:

```bash
curl -o /dev/null -s -w "connect=%{time_connect} starttransfer=%{time_starttransfer} total=%{time_total}\n" https://your-domain.com/admin
```

---

## Demo data cleanup

### Local or disposable environment

If you want a totally clean reset with fresh demo data:

```bash
php artisan migrate:fresh --seed
```

This will:

- delete all existing tables
- rebuild the schema
- reseed defaults, admin users, and demo data

### Local or disposable environment without demo data

If you want defaults and bootstrap accounts but do not want the demo inventory / shows / payouts, comment out `DemoDataSeeder` in `database/seeders/DatabaseSeeder.php`, then run:

```bash
php artisan migrate:fresh --seed
```

### Production warning

Do **not** run `migrate:fresh` in production.

If you need to remove demo data from a live database, do it intentionally with backups first. The safer pattern is:

1. back up the database
2. identify which records came from demo seeding
3. delete them with targeted SQL or an application-specific cleanup script

If you ever want a dedicated cleanup command, add a custom artisan command instead of relying on manual table deletes.

---

## Branding defaults

Current bundled defaults:

- Brand name: `Vortex Breaks`
- Primary color: `#29E7E7`
- Default logo asset: `public/images/vortexbreaks-logo.webp`

You can override these in **Admin > Settings** by uploading a custom logo or changing the primary color.

---

## Review mode checks

If the Review button is missing:

1. confirm the `reviews` module is enabled in **Settings**
2. rebuild frontend assets:

```bash
npm run build
php artisan optimize:clear
php artisan optimize
```

3. hard refresh the browser with `Ctrl+F5`

The Review button is designed to appear beside the Tour button in the top-right admin action rail.

---

## Common maintenance commands

Clear and rebuild caches:

```bash
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
```

Run migrations:

```bash
php artisan migrate --force
```

Restart queue workers:

```bash
php artisan queue:restart
```

Watch app errors live:

```bash
tail -f storage/logs/laravel.log
```

Check failed jobs:

```bash
php artisan queue:failed
```

Retry failed jobs:

```bash
php artisan queue:retry all
```

---

## Suggested update logging process

After each real change:

1. add a short entry to [CHANGELOG.md](./CHANGELOG.md)
2. note any required deploy commands
3. note any required data migration or cache clear
4. mention whether the change is UI-only, backend-only, or both

That keeps handoffs much easier later.

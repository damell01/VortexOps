# VortexOps — Claude Code Reference

Internal operations platform for Vortex Breaks (Whatnot sports card break business).  
Stack: **Laravel 13 + Filament v5 + PHP 8.3 + MySQL 8.4** (SQLite `:memory:` for tests).

---

## Quick start

```bash
cp .env.example .env          # set APP_KEY, DB_*, WHATNOT_* at minimum
php artisan key:generate
php artisan migrate --seed
php artisan serve
php artisan queue:work         # separate terminal — needed for notifications
```

Docker (production-like):
```bash
cp .env.example .env.docker    # fill in values
docker compose up -d
```

---

## Running tests

```bash
php artisan test               # uses SQLite :memory: (phpunit.xml)
php artisan test --filter ReceivingService
```

Tests use `RefreshDatabase`. Spatie roles are **not** seeded between tests — never rely on `assignRole()` in tests; use `isOwner()` (email check only) or direct model factories.

---

## Key patterns

### Filament v5 custom pages
Use `getView(): string` — **not** `protected static string $view`. The static property conflicts with the parent class and causes a PHP fatal.

```php
public function getView(): string
{
    return 'filament.pages.my-page';
}
```

### Module gating (`AdminModules`)
Every resource declares `protected static string $moduleSlug = 'streams'` and uses `HasModuleAccess`. Disabling a module fully hides it from navigation and blocks its routes — no extra route protection needed.

Only the owner (`dbellcreations@gmail.com`) can see the module toggle UI and enable/disable modules.

```php
AdminModules::isEnabled('inventory');  // memoized per request
AdminModules::flushMemo();             // call after saving settings
```

### Owner vs Admin
- `User::isOwner()` — email check, single super-user (module toggles, streamer balances widget)
- `User::isAdmin()` — Spatie role `admin` (most admin pages)

### Filament schemas (v5)
Use `Schema` and `Filament\Schemas\Components\*` — **not** `Form` / `Filament\Forms\Components\*`. The namespace changed in v5.

### Lazy loading disabled
`AppServiceProvider` disables lazy loading in non-production. Always eager-load in service methods:
```php
$case->load('palletLine.inventoryItem');
```
Or pass already-loaded relations explicitly (see `ReceivingService::creditStock()`).

---

## Domain map

| Area | Models | Service | Filament |
|------|--------|---------|---------|
| Shows / streams | `Show`, `ShowStreamer` | — | `ShowResource` |
| Payouts | `Payout`, `WeeklyPayoutBatch` | `PayoutService` | `PayoutResource` |
| Inventory | `InventoryItem`, `InventoryLocation`, `InventoryStock` | `ReceivingService` | `InventoryItemResource` |
| Receiving | `Pallet`, `PalletLine`, `InventoryCase` | `ReceivingService` | `PalletResource`, `ReceivePallet` page |
| Vendors | `Vendor` | — | `VendorResource` |
| Streamers | `Streamer` | `PayoutService` | `StreamerResource` |
| Whatnot scrape | `WhatnotChannel` | `WhatnotScraper` | Settings page |

---

## Whatnot scraper

Playwright/Node.js script (`scripts/whatnot-scraper.cjs`) — no public API exists.

```bash
php artisan whatnot:import              # uses WHATNOT_LIMIT (default 50)
php artisan whatnot:import --debug      # saves screenshots to /tmp/whatnot-debug-*.png
php artisan whatnot:import --channel=1
```

Exit codes: `0` = success JSON on stdout, `1` = auth/nav error, `2` = selector miss.  
When exit code 2, update the `SELECTORS` object at the top of `scripts/whatnot-scraper.cjs`.

Required env vars: `WHATNOT_EMAIL`, `WHATNOT_PASSWORD`.

---

## Payout types

| Type | Key fields |
|------|-----------|
| `profit_share` | `profit_share_pct` |
| `pwe_labels` | `pwe_rate`, `label_rate`, `hourly_rate` |
| `hybrid` | hourly + profit_share + tips + `burden_rate_type/value` |
| `hourly` | `hourly_rate` × hours |
| `package` | flat `package_rate` |
| `flat_rate` | fixed `flat_rate` |
| `custom_formula` | expression string, Shunting Yard eval |

Channel routing: streamer's `channel_routing_rules` JSON array `[{channel, bank_label}]` — case-insensitive match against show's channel name → populates `routing_bank_label` on payout.

---

## Receiving workflow

1. Create `Pallet` (vendor, PO number)
2. Add `PalletLine`s (item, location, expected cases/units, unit cost)
3. Map lines (`mapLine`) — sets FK on PalletLine
4. Receive via barcode scanner OR "Receive All" per line  
   - `receiveCaseByBarcode(string)` — lookup by barcode field
   - `receiveAllCasesForLine(PalletLine)` — auto-generates stubs if none exist
5. `receivePallet(Pallet)` — validates all lines mapped, receives all

WAC is recalculated on every receipt: `((existing_qty × existing_avg) + (incoming_qty × unit_cost)) / total_qty`.

---

## Settings storage

All settings live in the `settings` table (key/value). Use `Setting::get($key, $default)` / `Setting::set($key, $value)`.

JSON arrays (notification users) are stored encoded: `json_encode([1, 2, 3])`.

---

## Scheduler / queue

- Queue worker: `php artisan queue:work --sleep=3 --tries=3 --timeout=120`
- Scheduler: `php artisan schedule:run` every minute (docker compose `scheduler` service)
- DB backup runs daily at 02:00 via `Schedule::command('db:backup')`

---

## Docker

```
app         → Apache + PHP, port 8080
worker      → queue:work
scheduler   → schedule:run loop (every 60 s)
mysql       → MySQL 8.4, health-checked
ollama      → profile: ai (opt-in)
```

Image tagged as `ghcr.io/damell01/vortexops:latest`.

`docker/php-entrypoint.sh` waits for MySQL (`WAIT_FOR_DB=true`), runs migrations if `RUN_MIGRATIONS=true`, creates `storage:link`.

---

## CI / CD (GitHub Actions)

| Workflow | Trigger | What it does |
|----------|---------|-------------|
| `.github/workflows/ci.yml` | push / PR to `main` or `develop` | Composer install, PHPUnit (SQLite :memory:) |
| `.github/workflows/deploy.yml` | push to `main` (manual dispatch too) | Build Docker image → GHCR → SSH pull+restart on VPS |

Required GitHub secrets for deploy:
- `GHCR_PAT` — personal access token with `write:packages`
- `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY` — SSH access to production server

---

## Gotchas

- `inventoryLocations_count` (not snake_case) — Filament `->counts('inventoryLocations')` keeps the relation name as-is.
- `storage/app/public` symlink — run `php artisan storage:link` once after first deploy; the entrypoint script does this automatically.
- Module memo is per-request; after `Setting::set('enabled_admin_modules', ...)` call `AdminModules::flushMemo()` or the old value persists for the rest of that request.
- Filament v5 form fields live in `Filament\Schemas\Components\*`; the old `Filament\Forms\Components\*` namespace alias may or may not be present — check before using.

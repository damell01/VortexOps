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

`WhatnotScraper.php` always calls `scripts/whatnot-runner.cjs` — the single entry
point for every mode. The runner reads `WHATNOT_BROWSER_BACKEND` and dispatches:

- `local` (default) → `scripts/whatnot-scraper.cjs`, the original Playwright/Node
  scraper — no public Whatnot API exists, so this is DOM scraping.
- `scrapling` → `scripts/whatnot-scrapling.py` (Python/Playwright), but only for
  the modes it currently supports (`analytics`, `orders-batch`, `shipments-batch`,
  `ledger`); anything else still falls back to `local` automatically.

Both backends drive the **same** persistent Chromium profile
(`storage/whatnot-browser-profile` for the Python backend; the Node backend keeps
its own cookie/profile handling) behind the **same** shared browser lock
(`WhatnotScraper::withBrowserLock()`) — never run both concurrently by hand.

```bash
php artisan whatnot:import              # uses WHATNOT_LIMIT (default 50)
php artisan whatnot:import --debug      # saves screenshots to /tmp/whatnot-debug-*.png
php artisan whatnot:import --channel=1
```

Exit codes (shared contract across both backends): `0` success (JSON on stdout),
`1` misc runtime failure, `2` selector/page-layout miss, `3` auth required
(login or a Cloudflare/anti-bot challenge), `4` rate limited.
When exit code 2 on the local backend, update the `SELECTORS` object at the top of
`scripts/whatnot-scraper.cjs`.

Required env vars: `WHATNOT_EMAIL`, `WHATNOT_PASSWORD`.

### Scrapling backend (`WHATNOT_BROWSER_BACKEND=scrapling`)

`scripts/whatnot-scrapling.py` uses Playwright's Python API directly (a plain,
unmodified Chromium — no stealth/fingerprint patching, no CAPTCHA/Turnstile
solving). Every navigation goes through `safe_goto()` → `classify_page()`, which
returns `AUTHENTICATED | LOGIN_REQUIRED | CHALLENGE_REQUIRED | RATE_LIMITED |
UNEXPECTED_PAGE`. Anything but `AUTHENTICATED` **stops the run** (exit 3/2/4) —
this backend detects challenges, it does not attempt to get past them. A
`CHALLENGE_REQUIRED` or `LOGIN_REQUIRED` stop means a human opens the profile
headed (`WHATNOT_HEADLESS=false`) and clears it by hand.

Before any data is returned, `verify_channel_context()` reads the active seller
username straight off the loaded page and fails closed (exit 3) if it can't
positively match the requested channel — it never infers the seller from the
requested name.

**Extraction is intentionally not ported yet.** Session/auth/channel-verification
are implemented and are the safety-relevant part of this backend, so those came
first. The four mode functions (`mode_analytics`, `mode_orders_batch`,
`mode_shipments_batch`, `mode_ledger`) currently just exit 1 with a message
pointing at the equivalent extractor in `whatnot-scraper.cjs` — porting Whatnot's
empirically-tuned DOM heuristics blind, without a live authenticated session to
validate against, risks silently corrupting revenue/payout data. Port and
validate one mode at a time, headed, before trusting it:

```bash
python3 -m pip install -r requirements-whatnot-scrapling.txt
python3 -m playwright install chromium

WHATNOT_BROWSER_BACKEND=scrapling
WHATNOT_PYTHON_BIN=python3
WHATNOT_HEADLESS=false   # keep headed until a mode is validated
```

Staged rollout: (1) session/profile startup → (2) `/dashboard/home` reached
authenticated → (3) channel verified → (4) `analytics` with `WHATNOT_LIMIT=1` →
(5) raise the limit → (6) one `orders-batch` show → (7) one `ledger` window →
(8) a normal scheduled import. Roll back to `WHATNOT_BROWSER_BACKEND=local` (or
just omit the var) at any point — the Node scraper is unaffected and stays the
default.

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

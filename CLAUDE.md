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

## Email Configuration (Resend)

VortexOps uses **Resend** for email delivery in production. Get your API key from [resend.com/api-keys](https://resend.com/api-keys).

### Setup

```bash
# .env
MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxxxxxxxxxxxxx
```

### Development

For local development, use `log` driver (emails logged to storage/logs):
```bash
MAIL_MAILER=log  # default — emails go to laravel.log
```

Or use `array` for testing:
```bash
MAIL_MAILER=array  # in-memory queue for tests
```

### Sending emails

```php
use Illuminate\Support\Facades\Mail;

Mail::to('user@example.com')
    ->send(new YourMailable($data));
```

Resend is configured in `config/services.php` and `config/mail.php`.

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
| Sheet import | `Product` | `ProductSheetImporter` | `ImportInventorySheet` page, `inventory:import-cost-sheet` |
| Handbook | — | `InventoryManual` (content) | `Handbook` page, `ExportController@inventoryManualPdf` |

---

## Whatnot scraper

Playwright/Node.js script (`scripts/whatnot-scraper.cjs`) — no public API exists.

```bash
php artisan whatnot:import              # uses WHATNOT_LIMIT (default 50)
php artisan whatnot:import --debug      # saves screenshots to /tmp/whatnot-debug-*.png
php artisan whatnot:import --channel=1
```

| Exit | Meaning | What to do |
|------|---------|-----------|
| `0` | Success — JSON on stdout | — |
| `1` | General/nav error | Read stderr |
| `2` | Selector miss | Update `SELECTORS` at the top of `scripts/whatnot-scraper.cjs` |
| `3` | Bot challenge or signed-out page | `php artisan whatnot:login` — the session lapsed |
| `4` | Rate limited | Wait; running more often makes it worse |

Exit 3 matters most: Whatnot sits behind Cloudflare, which answers an automated
browser with a challenge rather than the login form, so **the scraper cannot sign
itself in**. It reuses a session a human established. Before this code existed a
challenge surfaced as exit 2, sending you hunting for a form that was never served.

### Authentication

Cookie-based, never credential-based in practice:

```bash
php artisan whatnot:login                  # setup guide + status
php artisan whatnot:login --test           # is the stored session still good?
php artisan whatnot:login --cookie-file=…  # import a Cookie-Editor / storageState export
php artisan whatnot:login --paste          # paste the JSON straight in
```

Cookies land in `storage/whatnot-cookies.json` (override with `WHATNOT_COOKIES_FILE`)
and typically last 30–90 days. The persistent browser profile refreshes its own
session, so the bootstrap file is only re-read when the profile has no cookies or
the file's mtime is newer than the last load.

`WHATNOT_EMAIL` / `WHATNOT_PASSWORD` are optional and only used by the form-login
fallback, which Cloudflare generally blocks.

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

## Product sheet import

`ProductSheetImporter` reads a workbook into the catalogue and is shared by two
callers — deliberately, so the preview cannot describe different behaviour from
the write:

| Caller | What it does |
|--------|--------------|
| `ImportInventorySheet` page | Upload → `plan()` renders every row → `apply()` on confirm |
| `inventory:import-cost-sheet` | Same, with `--dry-run` for the plan and `--overwrite-prices` |

- Headers are found **by name, wherever they sit** (`PRODUCT NAME`, `SKU`, `Type`,
  `Auction or BIN?`, `Cost`, `Sale price / Target`). Only `PRODUCT NAME` is required.
- Matching is by SKU, then by trimmed lowercase name — so a re-import updates
  rather than duplicating.
- A price cell holding a formula is **skipped and reported**, never cast to 0.00.
- Costs, targets and `sold_as` already set are left alone unless `overwrite` is
  passed; `average_cost` is never written here — it belongs to receiving.

### `sold_as` (Auction / BIN / Both)

Free-text column on `products`, normalised on import (`buy it now` → `BIN`,
`either` → `Both`) but never rejected — an unrecognised value is stored as typed.
Pickers offer `Product::soldAsOptions()` plus whatever is in the data, so a
hand-typed value stays selectable and filterable.

---

## The handbook

`App\Support\InventoryManual` holds the content — sections, steps, field tables,
troubleshooting, screen index — and **both** the on-screen `Handbook` page and the
printed PDF render from it, so they cannot drift.

- Screenshots live in `public/guide/manual/` and are tracked in git.
- A step may carry `more` for a long form captured in parts.
- Tests enforce the standard: every step has a screenshot on disk, every step has
  a field table, every named field has an explanation, and no screenshot sits in
  the folder unreferenced. See `InventoryManualPdfTest`.
- Other modules get a handbook by adding a class of the same shape and a row in
  `Handbook::modules()`.

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

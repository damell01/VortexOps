# Latent page failures — sweep of 2026-08-13

Every admin GET route (106) was loaded as an admin. Five 500'd. All are
pre-existing: code paths that had simply never been exercised, which is why
they only surface as pages get opened up.

The "Download is starting" entries in the raw sweep (`/admin/export/*`,
`/admin/manifest-template`) are exports working correctly, not failures.

| Page | Exception | Cause |
|---|---|---|
| `/admin/barcode-printer` | `TypeError` | `BarcodePrinter::$selectedItems` is typed `Eloquent\Collection` but assigned a `Support\Collection` |
| `/admin/inventory-report` | `LazyLoadingViolationException` | lazy-loads `streamer` on `InventoryLocation`; lazy loading is disabled outside production |
| `/admin/manager-hub` | `ErrorException` | `Undefined variable $stats` in its Blade view |
| `/admin/mobile-scanner-pro` | `Error` | see log; same family as the scanner pages |
| `/admin/pallets/create` | `Error` | see log |

Related, found in the same sweep:

- `MissingItemReportResource::$navigationIcon` is typed `string` but the parent
  declares `BackedEnum|string|null`, so the class fatals when loaded.
- `App\Console\Commands\SetupRoleVisibility` references `\Filament\Pages\X`
  and `\Filament\Resources\X` for ~10 classes that live under
  `\App\Filament\...`. The command fails on those.
- `streamer_log_entries.approval_status` is NOT NULL with no default, so
  writing null throws. Worth a default or making it nullable.

## How to re-run

    php artisan route:list --json   # filter admin GET routes without params
    # then load each as an admin and record any status >= 500

The lazy-loading violations are the most likely to recur: `AppServiceProvider`
disables lazy loading outside production, so any `$model->relation` on a page
that was never opened will fatal the first time someone opens it.

# VortexOps Whatnot Desktop Collector

This collector runs the existing VortexOps Whatnot extraction logic from a Windows computer using a dedicated local Chrome profile, then uploads structured data to VortexOps through the authenticated collector API.

It collects, per enabled Whatnot channel:

- shows and show analytics
- orders/lots
- shipment and fulfillment metadata
- ledger transactions

VortexOps remains the system of record. The desktop computer only collects data and sends it to the server. The Whatnot password is never stored in this folder or uploaded to VortexOps.

## First setup

1. Keep this folder inside the VortexOps project so `../scripts/whatnot-scraper.cjs` is available.
2. Install Google Chrome and Node.js 18+.
3. Double-click `install.bat`.
4. Open `config.json` and set:
   - `api_url` to the VortexOps API base URL, ending in `/api`
   - `api_token` to the same value as `SCRAPER_API_TOKEN` on the VortexOps server
5. Double-click `Login to Whatnot.bat`.
6. In the dedicated Chrome window, log into Whatnot normally and confirm Seller Hub works. Close the entire dedicated Chrome window.
7. Double-click `Sync Whatnot.bat`.

The collector profile is stored under `%LOCALAPPDATA%\VortexOps\WhatnotCollector\ChromeProfile` by default. It is separate from normal Chrome browsing.

## Automatic sync

After a manual sync completes successfully, double-click `Install Automatic Sync.bat` to add an hourly Windows Task Scheduler job. Manual `Sync Whatnot.bat` continues to work as well.

The scheduled task runs under the current Windows account. If Whatnot expires the dedicated session, automatic runs stop with `LOGIN_REQUIRED`; run `Login to Whatnot.bat`, sign in again, close Chrome, and the next sync can continue.

## Configuration

`show_limit` controls how many shows/analytics records are refreshed per channel on each run. Default: 50.

`order_batch_size` controls how many shows are included in each order/shipment upload. Default: 10. Smaller batches keep uploads well below normal web-server body limits and make partial recovery easier.

`ledger_days` controls the rolling ledger refresh window. Default: 31 days. Ledger rows are deduplicated on the server, so overlap is intentional.

`channels` can be left empty to use all active VortexOps channels with Include in Import enabled, or set to an array of Whatnot usernames to limit a computer to selected channels.

`chrome_path` and `profile_dir` can normally be left blank. The collector auto-detects standard Chrome installs and creates its dedicated profile under Local AppData.

## Safety boundaries

Each scraper subprocess is launched with the requested channel. The existing scraper refuses to collect until the active Whatnot seller identity has been positively verified. The collector reads that positive `CHANNEL_CONTEXT_VERIFIED` result and sends both requested and verified usernames to VortexOps. The server independently compares them before importing anything.

The server also resolves the channel from the verified Whatnot username; it does not trust a desktop-supplied numeric channel ID.

Orders retain the existing magnitude safety check: if an order scrape returns wildly more rows than the show's analytics order count, the server rejects that bundle rather than treating an apparently unfiltered order list as belonging to one show.

## Data flow

```text
Dedicated Chrome profile on Windows
        |
        v
scripts/whatnot-scraper.cjs
        |
        +-- analytics
        +-- orders-batch
        +-- shipments-batch
        +-- ledger
        |
        v
/api/whatnot/collector/import
        |
        v
Existing VortexOps Show / WhatnotShowOrder / Shipment / WhatnotLedgerEntry data
```

The server-side importer uses the same existing order and shipment persistence methods as the VPS scraper so the VortexOps forms, mapping flows, fulfillment data, and downstream ledger/payrun logic continue to consume the same database records.

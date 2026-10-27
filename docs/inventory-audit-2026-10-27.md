# Inventory repair and access audit — 2026-10-27

## Scope and safety

Laravel/Filament inventory, scanning, moving, receiving, quick add, catalog actions, counts/reconciliation, navigation, location visibility, dashboards and shared mobile UI. Tests use migrated disposable SQLite/MySQL databases. Browser fixtures refuse to run outside APP_ENV=testing and the inventory-audit SQLite database. No production inventory was modified for testing.

## Root causes and repairs

| Area | Root cause | Repair |
| --- | --- | --- |
| Bulk movement | Wrong inventory item foreign key and invalid Collection lookup | Correct keys and destination lookup; positive quantity validation; one transaction for the batch |
| Scan responsiveness | MutationObserver repeatedly rewrote its own observed markup | Change-only DOM rendering; repeated scans queued sequentially |
| Duplicate/failed scans | Overlapping handlers and redundant lookup requests | One submission path; sequential requests; recover after failure without automatically retrying uncertain writes |
| Camera lifecycle | Navigation and manual scan events could leave handlers/state behind | Cancelable consumed scan events and navigation/destroy cleanup; existing decoder retained |
| Unknown barcode sheet | Repeated event registration and out-of-order search results | Singleton handlers, stale-result guards and close cleanup |
| Stock correction | Blank values cast to zero and relative updates read before lock | Explicit finite numeric validation and locked relative adjustments |
| Receiving history | Consolidated receipt left stale quantity_after; intervening changes ignored | Update final balance and consolidate only contiguous compatible receipts |
| Reconciliation | Deferred selectors did not update count fields; entire item list loaded | Live selectors, validated counts, capped searchable results |
| Phone movement buttons | Global bottom navigation overlapped operation bar | Hide global bar while stock operation controls are present |
| Stuck global loader | pageshow restoration handler ran once | Persistent restoration cleanup |
| Phone layout | Grid and report wrappers exceeded narrow viewport | Single-column cards and bounded report/modal containers |
| Tools navigation | Links lived in a separate tools tab | Permission-aware grouped desktop/mobile menu; pages retained |
| Location gating | Admin bypass and unrestricted selector builders exposed other locations | Main plus assigned locations for all non-owner accounts; owner-only exception |
| Item deletion | Location-scoped stock could hide positive stock elsewhere before soft deletion | Block deletion when positive stock exists outside the actor's allowed locations |
| Streamer mutation | Role/profile combinations could restore write access | Central read-only guard, policies and server actions; no card buttons or action strip |
| Cross-user dashboard values | Cache keyed only by channel; raw totals ignored location access | Cache by channel and allowed locations; scope raw aggregates |
| Historical totals | Global snapshots lacked reliable location scope | Use reliable location breakdowns; omit unsafe legacy history/global comparison widget for restricted users |
| Snapshot breakdown | location_id/item_id aliases were not actual stock foreign keys | Correct inventory_location_id/inventory_item_id; eager-load location owner; include stock-outs |
| Backend bookkeeping | UI visibility scope could hide a transfer's other side internally | Transaction-local unrestricted service context, while UI authorization remains restricted |

## Final access contract

- Application owner: all inventory locations.
- Every other account, including admin: Main Warehouse plus locations assigned through its team profile.
- Streamers: read-only, including a streamer who also has admin role. No add, edit, delete, move, correction, barcode mutation or inventory-card buttons.
- Staff/admin assigned locations do not default to a streamer-only filter.
- Background stock processing retains complete bookkeeping access. UI location restrictions do not silently change financial/business jobs.

## Verification evidence

GitHub Actions runs backend inventory regressions, navigation/permission checks, existing full SQLite/MySQL CI and actual Playwright application workflows in desktop Chromium, mobile Chromium and mobile WebKit. The application browser suite exercises scan → move → persisted correction, continuous scans, unknown barcode recovery, transfer cancel/reopen/save, standalone quick add, receipt persistence, camera manual fallback, 17-page render/overflow crawl and cross-user access/card restrictions.

Before the additional access changes, the dedicated inventory suite passed 577 tests / 3,262 assertions and full SQLite/MySQL CI passed. The follow-up access run passed 586 tests / 3,315 assertions. Final deletion safeguards and phone card layout changes undergo another exact-head regression run. Exact final run results must be checked on the PR before merge.

Measured checks: scanner idle DOM mutations settle to zero over the 500 ms browser observation window; a repeated location visibility lookup performs zero additional database queries within the request. Report snapshot recent-movement lookup is grouped rather than one query per stock row. No production throughput/latency numbers are asserted.

## Practical limits

Automated browser camera/manual tests and decoder lifecycle tests do not certify physical camera autofocus, permissions or barcode recognition on every handset. Live production load and handset camera performance require field observation. Legacy snapshots with malformed or missing per-location breakdown cannot be safely reconstructed; restricted viewers omit those historical points. The organization-wide inventory-versus-revenue comparison is available to the owner because its stored source has no location breakdown.

Merge and deployment are separate: verify PR merge SHA and deployment workflow/health output before claiming production deployment. Passing a build alone is insufficient.

# VortexOps application performance audit — 2026-11-10

## Scope and evidence
Source-level review of selected high-traffic modules on the Dev branch, with GitHub repository inspection. **Not** a complete runtime audit: no browser trace, production SQL EXPLAIN, queue/CPU metrics, or deployment access was available. Findings are risk assessments, not measured speedups.

## Changes completed in this audit pass
- Operational Fulfillment Home widgets: `wire:poll.20s/30s/60s` now use `.visible` to avoid refreshing offscreen islands.
- Fulfillment Operations and Shipment Overview: `wire:poll.30s` now use `.visible`.
- Earlier Dev work: instant visual dismissal for Add/Edit Shift, 10-minute app update polling while visible, SQL aggregation for fulfillment dashboard, ledger report lazy-loading, and index migrations.
- Retained fast polling on Whatnot Sync when jobs are active; job-progress freshness is business-critical.

## Findings by area
| Area | Evidence | Priority | Next verification |
|---|---|---|---|
| Modal responsiveness | Shift dialog used server-bound `wire:click` to close; Alpine immediate hiding added | High | Browser test Close, Cancel, backdrop, Escape, double-click |
| Fulfillment dashboard | Multiple independent 20s/30s/60s Livewire polling islands | High | Measure request/minute before/after and data freshness |
| Fulfillment Operations / Shipment Overview | Full-page 30s poll even when offscreen | High | Check request size, database queries and polling on hidden tabs |
| Whatnot Sync | Active 3s poll, idle 15s poll | Medium | Profile payload and database cost; keep active progress updates |
| Quick Messages | Already uses `wire:poll.visible.15s/30s` | Low | Monitor if message feature enabled |
| Global clock | 30s refresh across pages | Medium | Consider client clock with server reconciliation after correctness tests |
| Ledger | Deferred reconciliation reports; date-bound filters | High | Run p50/p95 timings, SQL EXPLAIN and verify financial totals |
| Streamer Analytics | Aggregation, caching, and index migration in progress | High | Verify query plans and compare reports against baseline |
| Inventory / Scanner | Exact-match barcode/UPC/SKU indexes staged | High | Validate real table/column names, index selectivity, scan latency |
| Navigation | Roles and menu filtering involve access checks | Medium | Count queries per page navigation, cache only safe user-specific data |
| Server | No production PHP-FPM/MySQL/OPcache metrics captured | High | Gather CPU, memory, slow-query log, queue backlog and worker saturation |

## Important migration caveat
The three performance migrations were authored using conditional table/column checks, **not validated against a live schema**. Confirm actual table names and existing indexes; avoid redundant indexes and schedule any large index build off-peak. A passing migration is not proof that a query uses the index.

## Release gates
1. Run `php artisan test`, `php artisan view:cache`, `npm ci && npm run build`, and migration status/dry-run on Dev.
2. Compare five warm and cold loads each for Ledger, Inventory, Streamer Analytics, Fulfillment and dashboard. Record TTFB, p50/p95, Livewire requests, SQL time, and query counts.
3. Check stock counts, ledger totals, roles, payroll, shift overlaps, and scanner workflows.
4. Merge only after branch reconciliation, CI and backups; verify deployed SHA and build assets.
5. Roll back on increased 500/504 errors or financial/inventory discrepancies.

## Limits
No claim is made that the entire app has been exhaustively audited, that tests passed, that migrations ran, or that production response times improved. Additional profiling is required before calling the six-phase project complete.

## Follow-up code pass — 2026-11-10

Repository inspection covered dashboard widgets, authentication redirects, fulfillment queue/run services and authorization, Whatnot analytics show matching, and ledger dropdown/report queries. This is a **targeted cross-module code pass**, not a complete file-by-file or runtime audit.

### Changes applied to both Dev and Production
- Whatnot analytics worker caches `shows` schema column metadata per process, avoiding repeated `information_schema.columns` lookups. Preserved case-insensitive show-ID ambiguity checks.
- Fulfillment-only users redirect directly to their fulfillment landing page after login, avoiding an intermediate dashboard load.
- Needs Attention and Upcoming Shows dashboard widgets are lazy, reducing blocking queries during initial navigation.
- Dashboard inventory KPI computation now aggregates product/stock totals in SQL rather than hydrating all active products and stock rows. Cost basis still prioritizes `average_cost` then `unit_cost`.
- Fulfillment run creation computes buyer/order/shipment counts once rather than twice; ready notifications reuse eager-loaded fulfillment assignments.
- Packing no longer depends on streamer report approval on either environment. Ordinary fulfillment members still need show assignments, and station URL authorization is enforced.

### High-priority unresolved issues
1. **Ledger 500:** the provided logs only contain slow-query warnings, not the exception. Capture the matching exception and stack trace before changing business logic.
2. **Database saturation:** the sample contains an 18.1s ledger-status DISTINCT query, 5s activity-log DISTINCT, 3.1s ledger SUM, and hundreds of millisecond-scale show lookups during sync. Capture `SHOW FULL PROCESSLIST`, slow-query EXPLAIN, MySQL buffer/CPU and lock waits while sync is active.
3. **Show lookup:** `LOWER(whatnot_show_id)` in multiple importers may inhibit ordinary index usage. Any optimization must preserve case-insensitive matching and detection of duplicate/cross-channel IDs. Consider a normalized persisted ID with an indexed unique/multi-column key after data audit.
4. **Inventory dashboard KPI:** compare SQL aggregation to the previous PHP implementation on representative products, including deleted products, null costs, negative stock and multi-location rows. Verify SQL query plan and index use.
5. **Ledger dropdowns:** options are cached for 600s, but cache misses can run costly full-table DISTINCT scans. Verify indexes on `status` and `transaction_type` and ensure cache backend is persistent across requests.
6. **Authentication:** measure actual login request duration, middleware, role checks, session storage, and redirect destination. Direct fulfillment redirect helps only fulfillment accounts.
7. **Release verification:** compare Dev and Production schemas and deployment commits, run tests and smoke checks, and confirm background workers restart to pick up worker-lifetime schema caching.

No live production tests, deployment, MySQL EXPLAIN, or benchmark results were available for this pass.

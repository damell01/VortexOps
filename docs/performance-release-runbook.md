# VortexOps performance release runbook

This is a deployment and verification checklist, not evidence of a successful production rollout.

## 1. Establish a baseline (Dev and Production separately)
- Record the current commit SHA, PHP version, DB engine/version, queue driver and cache driver.
- Capture HTTP TTFB and total duration for Ledger, Inventory, Fulfillment, Streamer Analytics, and Dashboard at least five times each.
- Capture PHP-FPM worker saturation, CPU, RAM, swap, disk I/O, MySQL slow query log, and queue backlog during normal use.
- Inspect Laravel logs for `Slow database query` (>=300 ms) and rank by total time, frequency, and page.
- Use `EXPLAIN` on the top offenders; do not add indexes solely from intuition.

## 2. Verify Dev before migrating
```sh
composer validate --no-check-publish
php artisan test
php artisan view:cache
php artisan route:cache
npm ci && npm run build
php artisan migrate:status
```
Run these on the real Dev environment with its configured database. Revert generated caches after tests if necessary.

## 3. Schema and migration safety
- Confirm every new table/column against the actual database.
- Review existing indexes using `SHOW INDEX FROM table_name` (MySQL).
- Inspect migration SQL and index sizes. Schedule large index builds off-peak.
- Take a tested database backup and record rollback procedure.
- Apply migrations to Dev first, then repeat the page benchmarks and EXPLAIN plans.
- Validate financial totals, stock quantities, permissions, and scheduling before considering Production.

## 4. Performance acceptance
- Compare p50/p95 response time, DB time and query count against baseline.
- Test both warm-cache and cold-cache loads.
- Exercise filters, pagination, scanner barcode lookup, payroll, inventory counts, and Livewire polling.
- Check that background sync, imports and queues do not starve interactive requests.
- Verify 1440x900, 1920x1080 and mobile layouts.

## 5. Production release
- Reconcile Production-only commits with Dev; do not blindly merge diverged branches.
- Merge only after CI, tests, migration dry-run and business-data reconciliation pass.
- Deploy through the existing server pipeline, verify deployed SHA, run migrations once, rebuild caches and restart long-lived workers where required.
- Check 500/504 rates and query metrics immediately after deploy; keep rollback ready.

## 6. Server tuning (measure first)
- Check PHP-FPM max_children against RAM per worker; don't just increase workers.
- Ensure OPcache is enabled in production and Laravel debug tooling is disabled.
- Use an appropriate shared cache backend for multi-worker workloads.
- Inspect MySQL buffer pool hit ratio and slow query log before tuning.
- Monitor queue worker concurrency and overlapping scheduler jobs.
- Increasing proxy timeout is not a substitute for fixing a slow query.

**Never run production migrations or merge a draft PR based solely on this document.**

# Ledger attribution and dashboard — production verification

These changes are committed to the Production branch but must be tested on the VPS or staging host before being considered deployed. No payroll or attribution writes are part of the UI changes.

## Preflight
```bash
cd /var/www/vortexops
git status --short
php -l app/Filament/Resources/WhatnotLedgerResource/Pages/ListWhatnotLedgerEntries.php
php -l app/Filament/Resources/WhatnotLedgerResource/Pages/LedgerTips.php
php artisan view:clear
php artisan view:cache
php artisan test --stop-on-failure
```
Do not deploy if any command fails. Capture errors without deleting active backfill logs.

## Manual checks
1. Open Ledger tip totals for the same period/channel as the ledger. Note tip-only unverified business count and net dollars.
2. Click **Review unmatched tips**. Confirm only tip/tips transactions lacking an approved non-null business attribution appear.
3. Confirm a tip with an approved business is absent even if its show/streamer assignment is incomplete.
4. Confirm a pending or rejected assignment remains in review. The details explain its status.
5. Open a row with no order ID, a row with no verified show, and one with a verified show. Ensure no speculative show/business assignment is described as verified.
6. Verify that approval by the submitting administrator is rejected; second admin approval is required. Do not actually approve production records merely for UI testing.
7. Test period, channel and search filters, CSV export, and small screens (375px, 768px, desktop).
8. Verify dashboard shortcuts and permissions; non-admin roles must not gain ledger access.
9. Compare performance before and after with the same filters, warmed caches and background workload; inspect slow query log if page remains slow.
10. Confirm no messaging or scanner feedback feature was enabled.

## Read-only reconciliation query (MySQL)
```sql
SELECT COUNT(*) AS review_count,
       ROUND(COALESCE(SUM(le.amount), 0), 2) AS review_net_dollars
FROM whatnot_ledger_entries le
WHERE LOWER(TRIM(le.transaction_type)) IN ('tip', 'tips')
  AND le.created_date >= '2026-11-03 00:00:00'
  AND le.created_date <= '2026-11-09 23:59:59'
  AND NOT EXISTS (
      SELECT 1 FROM ledger_attributions a
      WHERE a.whatnot_ledger_entry_id = le.id
        AND a.status = 'approved' AND a.business_id IS NOT NULL
  );
```
Adjust dates and channel scope to match the selected UI. This query includes all imported statuses. Amounts in ledger entries are dollars, while LedgerTipReport internally aggregates cents. The page warning now displays the dollar-based result directly.

## Rollout
Prefer dev first, then production. Pull only after a clean git status and verified branch. Run `php artisan optimize:clear && php artisan view:cache`. Confirm the app is responsive and no new 500s in Laravel logs. No migration required for these view/filter changes.

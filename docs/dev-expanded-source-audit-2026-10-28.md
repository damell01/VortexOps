# VortexOps Dev — Expanded source audit, 2026-10-28

## Scope / honesty
Static inspection through GitHub's file-fetch interface across approximately 60 specific source files and searches spanning inventory, receiving, scanner, shows, payroll, payouts, ledger, loans, timekeeping, users/roles, dashboards, settings, notifications, Whatnot ingestion, reports, navigation, policies, services and routes. Search results may reflect the indexed/default branch, so individual file reads explicitly used `ref=Dev`. Some indexed paths (e.g. ledger attribution and inventory price-alert classes) were **not present** on Dev. No complete repository tree listing or clone was available to this reviewer. **Therefore this is a broad cross-app review, not a certified complete file-by-file scan.** No PHP/JS tests or authenticated UI tests were run.

A whole-checkout scanner has been added at `scripts/audit_app_source.py` for use in a Dev checkout. It inventories all eligible source files and flags heuristic review candidates; the script has not been executed against the full checkout.

## Direct fixes committed
1. `InventoryItemResource/Pages/ManageStock.php`: validate adjustment and transfer input and reject invalid operation state; improve preview feedback.
2. `app/Filament/Pages/StockTransfer.php`: bulk transfers now validate all quantities and execute inside an outer database transaction. Previously a mid-loop failure could leave earlier items transferred despite an overall failure notice.
3. `app/Filament/Pages/InventoryCount.php`: reject invalid physical counts rather than coercing malformed strings to zero in individual and bulk save.
4. `resources/js/app.js`: stop globally suppressing unsaved-change unload handlers.
5. `tests/ui/sitewide-layout.spec.js`: add responsive audit with screenshots, redirect detection and clipped-control checks.

## Source findings and follow-up risks
| Priority | Module | Evidence | Action |
|---|---|---|---|
| P1 | Inventory count | `InventoryCount::saveAll` applies multiple stock adjustments one by one | Consider all-or-nothing transaction, test failure rollback and large count batches; no silent partial save |
| P1 | Bulk transfer | `StockTransfer` had per-item service transactions without outer transaction | Fixed on Dev; verify DB rollback behavior and permission/visibility for source/destination |
| P1 | Stock services | `InventoryService::adjustStock` writes absolute count with row lock; transfer locks source row | Preserve locking; check all callers and service-level validation |
| P1 | Receiving | `ReceivingService` uses case locks and batch receiving; `ReceivePallet` has saved drafts and scan IDs | Test duplicate scan idempotency, partial receiving, session refresh and case allocation |
| P1 | Payroll | `PayoutService` has batch finalization, `PayRunAutomationService` uses row locks and business scoping | Avoid formula refactor without regression tests; verify cross-business pay-run inclusion |
| P1 | Pay run readiness | `PayRunReadinessService` checks show blockers, missing costs/hours, stale source timestamps | Keep blockers visible; test stale data and reportless shows, separate show visibility from payout eligibility |
| P1 | Roles | `ShowResource` adds row scoping and its own edit rules while `ShowPolicy` is permission-based | Test policy/resource agreement for each role and action; do not relax permissions |
| P1 | Inventory roles | `InventoryItemPolicy` permits streamer creation and location-scoped editing | Verify this is intended against actual resource gates and UI shortcuts |
| P1 | Timekeeping | `TimeTrackingService` uses transactional clock-in/out with locks | Verify concurrent clock-in prevention and show assignment rules; preserve audit trail |
| P2 | Scanning | `InventoryScanner` is large and has lookup, adjust and scan actions; `ScanningService` tracks sessions | Reduce redundant queries and consolidate scan feedback after profiling |
| P2 | Dashboards | `DashboardShortcuts` branches streamer/nonstreamer, not separate assistant role | Evaluate assistant/fulfillment shortcuts and visibility, without expanding access |
| P2 | Ledger | Internal `LedgerResource` and `WhatnotLedgerResource` coexist | Clarify accounting-vs-Whatnot source labels and reconciliation status |
| P2 | Loans | `StreamerLoanResource` and repayment records exist | Surface repayment cadence/balance/history in one view; preserve calculations |
| P2 | Notifications | `NotificationRouter` routes recipients/channels and supports caps | Audit duplicate delivery, per-role visibility and polling cost |
| P2 | Reports | `ProfitLossReport` and `ReportsOverview` aggregate business/show data | Check filter and period consistency, business scoping, export parity |
| P2 | Whatnot | `WhatnotSyncEngine` and scraper/backfill commands exist | Verify lock scope, retry behavior, freshness and duplicate import handling |
| P2 | UI | Filament SPA/prefetch, custom JS and multiple CSS layers | Consolidate incrementally, not via a blanket rewrite |
| P2 | Settings | `AppSettings` and `NotificationSettings` mutate configuration | Verify role authorization and invalid input handling on every action |
| P3 | Source structure | Some resource classes are tens of thousands of characters; `PayoutService` exceeds 1,100 lines | Extract cohesive services/components only with tests |

## Release gate
Before merging to Production: run `python3 scripts/audit_app_source.py`, PHP syntax checks, PHPUnit/feature tests, frontend build, and authenticated QA for sensitive workflows. The current commits have not been executed in a running app.

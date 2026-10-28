# VortexOps Dev — Source-based usability and reliability review (2026-10-28)

**Scope and evidence:** Static review of the `Dev` branch only. No login, browser testing, PHP execution, database access, production deployment, or measured timings. This is an expanding code audit, **not a claim that every route or workflow has been tested**.

## Changes already made on Dev
- `resources/js/app.js`: removed blanket suppression of all `beforeunload` listeners; legitimate unsaved-form warnings can now operate. Verify stale warnings in a browser before release.
- `tests/ui/sitewide-layout.spec.js`: responsive viewport smoke test, overflow/clipped-control checks, login redirect detection, screenshots before assertions.
- `app/Filament/Resources/InventoryItemResource/Pages/ManageStock.php`: reject blank/malformed/nonfinite adjustment quantities, nonpositive transfer quantities, invalid operation values; improve pre-submit effect feedback.

## Source-reviewed workflow findings

### Inventory — P1
- `InventoryItemResource::addStockFields()` already groups quantity, unit cost, destination, date, and optional vendor/movement type. Keep one primary add-stock flow rather than duplicate actions.
- `ManageStock` combines adjustment, transfer, and send, and derives the available count from per-location rows. Pre-submit feedback exists; maintain it for all operations. **Proposal:** keep a single page but make the operation choice prominent and expose only the fields relevant to the chosen action. Verify current Blade behavior first.
- `InventoryService::adjustStock()` uses row locks and absolute count writes, which is a strong correctness safeguard. `transferStock()` locks the source row and rejects insufficient stock. Preserve these transactional rules.
- **Follow-up security review:** `InventoryService` accepts quantities from multiple callers; inspect all callers and decide whether service-level finite/positive quantity validation is appropriate. Do not change semantics without call-site coverage and tests.
- **Follow-up authorization review:** `InventoryItemPolicy` allows streamers to create items and update items with stock in assigned locations; compare this to resource-level role gates and actual intended staff permissions. Do not expand access as a UI shortcut.
- **UX proposal:** for stock corrections, display current count, entered new count, signed difference, location, and reason together immediately before submission. For transfers, display from/to/quantity and resulting source balance.

### Shows — P1
- `ShowResource` already separates Overview, Financials, Analytics, Approval in persisted tabs; retain task-specific grouping rather than creating another wizard.
- `getEloquentQuery()` eager-loads streamers/channel and scopes streamer-visible shows. Preserve scoped queries.
- Show deletion is guarded against orders, payouts, streamer relations and financial history. Preserve these safeguards.
- **UX proposal:** on the show view, present next actions based on status and permission, linking directly to relevant show-report, approval, or inventory actions. Keep business rules in existing services.
- **Review candidate:** `getNavigationBadge()` caches pending review counts by channel for 60 seconds. Confirm whether users with narrower visibility should see a scoped count; do not change without role expectations.

### Payroll — P1
- `PayrollOverview` uses Livewire URL-backed workflow/page/period state and resets queue pagination on filter changes; preserve this behavior.
- Pay run creation routes to `WeeklyPayoutBatchResource`; the page intentionally avoids duplicate header CTAs.
- **UX proposal:** consolidate readiness and blockers into a concise summary with links to the relevant show/report. Clearly separate *show occurred*, *report submitted*, and *payroll eligible*; never silently change inclusion rules.
- **Code review candidate:** inspect workflow-filter validation and queue search behavior for unexpected values and expensive queries.
- Financial calculations, payout status transitions, and loan deductions require dedicated unit/feature tests before any refactor.

### Ledger and loans — P1/P2
- `LedgerResource` describes internal accounting history and uses separate visibility rules; the Whatnot-fed ledger is a separate resource. Verify the intended distinction in navigation and terminology before changing either.
- `ListStreamerLoans` describes automatic deductions at pay-run time. Preserve repayment semantics; improve display of schedule, remaining balance and repayment history only after verifying data model.
- **UX proposal:** separate unassigned ledger items, house expenses, and assigned-to-show entries in a review queue with clear attribution status.

### Dashboards and roles — P2
- `DashboardShortcuts::forCurrentUser()` provides role-dependent entry points and checks access. It currently chooses streamer vs nonstreamer links and adds an admin report shortcut.
- **UX proposal:** audit whether assistants and fulfillment users need their own shortcut set, rather than inheriting the general nonstreamer receiving links. Verify role predicates and access before changing links.
- **Review candidate:** confirm that shortcut visibility agrees with page policies, module grants, and nav visibility for each role.

### Shared UI and performance — P2
- SPA navigation and prefetch are already enabled in the Filament panel. Optimize existing Livewire navigation; avoid a framework rewrite.
- Action-feedback, scanner lazy loading, and custom notification handling already exist. Audit lifecycle and double-listener behavior before adding duplicate JS.
- CSS has numerous overlapping imports. Consolidate incrementally with regression screenshots rather than bulk deletions.

## Recommended implementation sequence
1. Validate and harden all inventory entry points and stock-service call sites with unit tests.
2. Audit the Manage Stock Blade view and simplify operation-specific fields, previews and keyboard/touch affordances.
3. Audit show detail actions, role scoping and next-action navigation.
4. Audit payroll readiness, queue filters and pay-run review without changing calculations.
5. Audit ledger attribution, loans and role-specific dashboards.
6. Consolidate shared UI styles and JS only with evidence of safe behavior.
7. Run automated tests and static checks in a proper dev checkout before merge.

## Release gate
No production merge until PHP syntax/static checks, relevant PHPUnit/feature tests, JS build, and authenticated smoke tests are completed by a capable environment. Static review alone cannot verify visual behavior or correctness at runtime.

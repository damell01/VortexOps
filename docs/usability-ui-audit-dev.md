# VortexOps Dev — Usability-first audit backlog

Status: initial source review only. No authenticated browser walkthrough or performance benchmark has been completed. Do not label hypotheses as confirmed user-facing defects.

## Confirmed code findings

| Priority | Area | Evidence | Next action |
| --- | --- | --- | --- |
| P1 | Unsaved changes | resources/js/app.js registers a capture-phase beforeunload listener calling stopImmediatePropagation() for every event | Review intended dirty-form protection and replace blanket suppression with scoped lifecycle cleanup; test navigation with an edited form |
| P1 | SPA navigation | AdminPanelProvider enables spa(hasPrefetching: true) | Test custom menu links, Livewire navigation, back/forward, and modal state; fix only verified failures |
| P1 | Action feedback | resources/js/action-feedback.js provides global request hooks, timeout warnings and error messages | Test real slow requests, validation, 403/419/500 responses, and navigation to avoid false error notices |
| P2 | CSS maintainability | resources/css/app.css imports many overlapping mobile/table/UI stylesheets | Inventory selector collisions and specificity; consolidate incrementally with screenshot comparison |
| P2 | Notification interaction | AdminPanelProvider BODY_END hook installs custom click handlers and programmatically clicks notification button | Test after SPA transitions for double toggles and stale references |
| P2 | Scanner performance | resources/js/app.js lazy-loads scanner dependencies | Preserve lazy loading; benchmark first scanner open vs repeat open |
| P2 | Notification polling | AdminPanelProvider polls database notifications every 30 seconds | Check request overhead and expected freshness per role |

## Workflow audit order

1. Inventory: search, scan, receive, adjust, transfer, price update, location filters, deletion and error recovery.
2. Shows: scheduling, assignment, show reports, calendar and navigation to related records.
3. Payroll: draft generation, missing reports, earnings inclusion, review, finalization, historical access.
4. Streamer and assistant roles: clock in/out, upcoming shows, earnings, permitted inventory and leaderboard.
5. Ledger, loans, reports, settings and other discovered resources.

For each workflow capture: role, entry point, steps/clicks, screen count, errors, mobile behavior, proposed simplification, risk, priority and approval requirement.

## Browser verification

Run only against a disposable local/dev environment:

```bash
VX_BASE_URL=http://127.0.0.1:8000 VX_AUDIT_ROUTES='/admin,/admin/inventory-items' npx playwright test tests/ui/sitewide-layout.spec.js
```

Authenticated routes require VX_STORAGE_STATE pointing to a valid development test-account storageState JSON. Without it, login redirects may make tests pass without actually exercising protected pages: confirm the final URL and role before accepting results. Run role-specific passes. Screenshots are written to test-results/.

## Approval boundary

Safe, localized visual/accessibility fixes can be made on Dev. Present proposed changes to workflow structure, permissions, payroll calculations, inventory semantics, and irreversible actions for approval first. Never deploy to Production without authorization.

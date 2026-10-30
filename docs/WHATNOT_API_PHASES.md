# Whatnot API integration: phase tracking

## Current status (Dev branch only)
- Phase 1: database-backed per-channel credentials, encrypted token storage, owner-only health routes and Filament navigation, manually triggered API health checks.
- Phase 2: read-only transport for shows and orders and a CLI probe. **No ingestion or mapping yet.**
- Phase 3: audit table for future comparison counts. **No ledger writes or reconciliation logic yet.**
- Phase 4: **not implemented**; webhook signature verification and webhook receiver require confirmation of Whatnot's official payload/signing scheme.

## Safety gates before activation
1. Confirm official base URL and endpoint paths against the API specification, and test with a real seller token.
2. Confirm that each token belongs to its intended seller channel; a successful HTTP 200 alone does not establish correct seller identity.
3. Add API response fixtures and integration tests for pagination, 401/403, 429, 5xx and timeouts.
4. Add idempotent importers for shows, orders, shipments, and report files.
5. Compare API totals with scraper totals and record differences per channel and reporting period.
6. Never mutate approved payroll, locked shows, financial reports, or inventory without explicit reconciliation approval.
7. Add verified webhook signatures, replay protection, event idempotency, queue retries and scheduled catch-up.
8. Run full test suite and smoke-test Dev server, including existing scraper health.
9. Only then consider per-channel opt-in rollout; Production remains unchanged.

## Deployment
On dev server: pull Dev; run php artisan migrate --force; clear caches.
Then verify owner-only /admin/whatnot-api/dashboard and the existing scraper.
No API credentials are needed to render the dashboard.

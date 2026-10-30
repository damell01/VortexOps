# Whatnot Seller API rollout (Dev)

## Status
Infrastructure only. Existing Whatnot scraper and scheduled jobs remain unchanged. API synchronization is intentionally disabled.

## Owner-only endpoints
- GET /admin/whatnot-api/health — status for all channels
- POST /admin/whatnot-api/channels/{channel}/credentials — save encrypted token, keep sync disabled
- POST /admin/whatnot-api/channels/{channel}/test — explicitly test a configured connection

These routes require authentication and isOwner(). Never expose tokens in responses or logs.

## Rollout
1. Run migrations on the Dev server and verify owner-only access.
2. Obtain separate seller authorization for each of the four channels.
3. Confirm the official API base URL, authentication mechanism, and endpoint query schema against the live Whatnot API documentation before enabling connection tests.
4. Test each channel separately and validate returned seller identity.
5. Build API read-only importers with idempotent external-ID matching and dry-run comparison.
6. Only after field parity and financial reconciliation, enable sync per channel; keep scraper fallback.
7. Preserve locked financials, payroll, and approved COGS. Never overwrite them from API responses.

## Safety
No API calls occur automatically. No API scheduler or webhooks are installed. Saving credentials never enables sync.

## Environment separation (mandatory)
- Development and staging are UI / mocked-data test environments only. Do not run Whatnot browser scraping, import jobs, or real Seller API probes there.
- The Seller API client, snapshot capture and owner health check require BOTH APP_ENV=production and WHATNOT_SELLER_LIVE_REQUESTS_ENABLED=true. Default is false.
- Never copy, reset, relogin, or reuse the existing production Playwright browser profile or its cookies for Seller API authorization.
- The API uses independent bearer credentials. Saving a token leaves per-channel enabled and sync_enabled false.
- Keep WHATNOT_SELLER_PAGINATION_VERIFIED=false until official cursor semantics are checked. API routes and response fields remain unverified.
- Production rollout must use a separate controlled deployment. A GitHub Dev commit does not update the running server.

## Before merge to Production
1. Run PHP lint and the Whatnot feature/unit test suite in an isolated environment using mocked HTTP only. Never run browser login/scraper commands.
2. Check all new migrations and existing schema on a database copy; take a production backup before running migrations.
3. Verify the owner-only connection dashboard on Dev without a live token or external requests.
4. Verify no new scheduled job, webhook, or API call is activated by a deploy. Confirm the existing scraper scheduler and browser profile remain untouched.
5. Keep the API live-request flag false in Production after merging. Credentials may be entered later without enabling traffic.
6. After receiving keys, verify actual API host, authentication, channel identity, scopes, resource paths, pagination, rate limits, and available webhooks against official documentation.
7. Enable read-only capture on Production only after the API contract is verified; inspect schemas and compare with existing scraper data before mapping orders, shows, or ledger.
8. Require idempotent matching, rollback plans, financial discrepancy checks, and explicit approval before any operational imports or automatic writes.
9. Preserve the approved End of Stream inventory deduction rule. Whatnot order imports must not also decrement stock.

## Read-only operational commands
- `php artisan whatnot:api-audit` reports configured channels without contacting Whatnot.
- `php artisan whatnot:api-capture 1 orders --inspect` reads stored encrypted snapshots and emits only field names and counts.
- `php artisan whatnot:api-capture 1 orders --confirm-read-only` is a manual API request and requires the Production-only global gate. Do not run before endpoint verification.

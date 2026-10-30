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

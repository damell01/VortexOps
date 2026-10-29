# Operations verification — October 29, 2026

## Completed

- Streamer assistants have only the clock dashboard and their own paid pay-run history. Other admin pages are blocked, including direct show links and previously saved navigation grants. Their approved clock minutes feed normal business-scoped pay runs at the configured hourly rate.
- Inventory notification review preserves updates arriving while the popup is open. Unread changes remain available across logins until acknowledged. Sold-out events are labeled correctly.
- Move Stock: restored the scanner shortcut and fixed unrestricted administrator movement visibility. Restricted streamer location visibility remains enforced. Read-only streamers cannot invoke the catalog Add Stock action.
- Inventory tool navigation and scan/confirm workflows have automated coverage. Rapid scans are lookups; stock changes require an explicit confirmation.
- Tip reporting tests verify one primary show recipient, refunds, cents, and excluded ambiguous/unmatched orders. Ledger production logic was not changed.
- Other Pay tests verify additions to existing draft pay-run lines, totals and audit notes; zero amounts and finalized runs are rejected.

## Evidence

- Assistant functional browser checks: 3 passed across desktop Chromium, mobile Chromium, and mobile Safari.
- Assistant PHP checks: 9 passed, 67 assertions.
- Popup review regression checks: 8 passed, 15 assertions.
- Stock, permissions, scanner, reporting, Other Pay, and navigation checks: 58 passed, 253 assertions.
- Camera scanner unit checks: 6 passed. Modal lifecycle check and production asset build passed.
- Live PHP 8.4 read-only checks: inventory catalog 200; assistant dashboard 200 with clock and paid history; assistant shows URL 403. No real clock or payroll records were changed.

## Remaining limits

- Ledger corrections and assignments remain a separate project.
- Other Pay remains a manual adjustment: apply after recalculating payouts under the existing behavior.
- The wider historical suite is not wholly green. A pre-change baseline had 49 failures; several tests still assume retired scanner payloads, Quick Add navigation, or older access rules.
- Automated browser checks cover the inventory page sweep, responsive overflow, scan/confirm/transfer/correction flows, overlay lifecycle, and assistant dashboard. Physical scanner/camera hardware and every possible production data combination still require real-device verification.
- Duplicate streamer profiles were not reopened.

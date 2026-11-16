# VortexOps — ideas list

What we plan to build next, and ideas we've talked about but not picked yet.
Move an idea to **Done** (with the date) once it ships.

## Planned

### Monthly owner report (PDF, emailed on the 1st)
An automatic report for the owner covering the month just ended, emailed as a
PDF on the 1st (via Resend) and also downloadable from Reports.

- P&L for the month: gross sales, Whatnot net, product cost, payroll, net profit, with month-over-month change
- Top streamers and top shows by net
- Channel breakdown
- Inventory value at month end, plus slow movers (no sale in 45+ days)
- Goal progress for the month
- Pay runs paid in the month, and anything still unpaid
- Uses the same numbers as the Reports pages (ProfitLossReport, GoalProgress), so the PDF and the screens can't disagree

## Candidates

- Alert when a deploy fails (push or email: "Production didn't deploy: N tests failing")
- Pay run "what changed" view before approving (each person vs last run, and why)
- "Needs your approval" inbox across reports, pay runs, adjustments, ledger corrections and sign-offs
- Push notifications: report due, pay approved, show starting soon
- Morning digest card or email: yesterday's net, missing reports, blockers, low stock, sync health
- Pre-show checklist (inventory pulled, show on the right channel, last report done)
- Streamer pay history chart (weekly earnings, last 3 months)
- End-of-stream report in under a minute (pre-fill everything Whatnot knows)
- Audit trail on money edits, shown on the record
- Scan-first camera on receiving, counts and packing
- Reorder suggestions from sales speed
- Stock count mode (scan a location, approve the differences)
- Chargeback/refund early warning per buyer or show
- Shipping label cost vs expected, flag overcharges
- Weekly streamer scorecards pushed to their phone
- Slow-mover alerts with a suggestion (bundle / giveaway / discount)
- One-tap "repeat last show"
- Repeat-buyer list for shout-outs
- Offline-tolerant forms, saved filters, global jump-anywhere search

## Done

- 2026-11-16 — Template check before deploy (`php artisan views:check` in CI)
- 2026-11-16 — Streamer estimated pay for the current pay period
- 2026-11-16 — Tabs and step-by-step wizards for long forms

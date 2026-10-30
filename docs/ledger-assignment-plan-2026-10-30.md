# Ledger assignment plan — October 30, 2026

This is a plan from the current source, not a fresh production data census. Implement after reviewing the workflow below. Do not run a bulk rematch or modify paid payroll as part of this plan.

## Reuse the existing foundation

LedgerAttributionService already supports destinations show, streamer, house and unassigned; categories tips, sales, refunds, fees, shipping, giveaways, bonus, marketing and other. It stores pending corrections and requires a different administrator to approve/reject. LedgerAutoMatcher respects human decisions and only approves matches supported by independent source identifiers. ledger_attribution_events records changes. Audit and candidate-export commands already exist. First verify their UI, reporting consumers and import provenance rather than rebuilding these services.

## Identifier map

| Identifier | Existing location | Use and trust rule |
|---|---|---|
| Ledger record ID | whatnot_ledger_entries.id | Stable internal correction/audit target; not a show identifier. |
| Import deduplication key | whatnot_ledger_entries.dedup_key | Avoid duplicate financial events on repeated import; not proof of ownership. Verify its construction and source-account scope before changing it. |
| Source account/channel | whatnot_channel_id on ledger, show and orders through shows | Required ownership boundary. Conflicts go to review; never silently substitute the selected navbar channel. Audit historical source-account mapping separately from the business. |
| Source livestream ID | ledger/order raw_data keys live_id, whatnot_live_id, livestream_id, source_live_id; shows.whatnot_show_id | Strongest current show evidence: explicit source-provided ID, exactly one show, same channel, no conflicting explicit IDs. A URL or importer-selected show ID is not equivalent. |
| Source order ID | ledger.whatnot_order_id; whatnot_show_orders.whatnot_order_id | Financial-event-to-order link. Resolve to a show only with independently verified order-level livestream provenance. One order can have multiple legitimate ledger events (sale, tip, refund, fee); do not deduplicate them by order ID alone. |
| Order hash / detail URL | ledger.order_hash; whatnot_show_orders.whatnot_order_detail_url | Candidate corroboration and navigation to source evidence. Not independent proof of the originating show. Do not promote a URL-derived livestream ID to verified evidence. |
| Listing ID | ledger.listing_id | Useful product/order corroboration where the source provides it. Listings may recur across sales/shows; not a unique show key. |
| Shipment ID | whatnot_show_orders.whatnot_shipment_id / raw shipment_id | Helpful related-order evidence; combined shipments can span orders/shows. Not a show ownership key by itself. |
| Person ID | streamers.id; shows' streamer pivot is_primary; streamers.user_id | Use IDs, not display names. A verified show has a tip recipient only when exactly one primary streamer is assigned. Missing/multiple primaries go to review. Assistants are not inferred recipients from time overlap. |
| Business ID | streamers.business_id; ledger_attributions.business_id | Financial destination. A channel and business are different concepts. Record the approved business so later profile reassignment cannot silently rewrite historical reporting. |
| Dates, amounts, item text | ledger.created_date/completed_date/amount/message; order prices/item_name; show dates | Rank candidate matches only. Settlement can lag the show, timezone boundaries vary, and amounts differ due to fees/refunds/tips. Never auto-approve from a date/amount/name coincidence. |

## Matching sequence

1. Preserve imported ledger amounts and raw payloads. Resolve source-channel provenance and deduplication before attempting ownership.
2. Check explicit source livestream ID plus channel. Reject conflicts and duplicate show identifiers into review.
3. Otherwise match source order ID to independently verified order payload livestream ID plus channel. Historical show-order links alone remain candidates.
4. Resolve verified show to its unique primary streamer and business. Keep a valid show match separate from an unresolved recipient.
5. If no verified match exists, offer ranked candidates with reasons: exact order, hash, listing, shipment, channel, dates, amount and text. Label each fact as verified or corroborating. Keep unmatched as a valid state; do not default to house.
6. Allow manual assignment to a show, a person, a house business/category, or unassigned, with a required reason. Respect existing human decisions on all future automatic passes.

## Review and posting workflow

- Inbox tabs: unmatched, conflicts, missing primary, pending approval, approved, rejected. Show entry count and signed dollar amount separately, with channel/business/date/type filters.
- Detail: immutable source event beside proposed destination; candidate evidence; before/after totals; related order/refund events; audit trail.
- Two-person approval is the existing default. Verify server-side permissions and channel/business boundaries. Keep rejection and superseded corrections visible. Add revision checking to reject stale concurrent reviews.
- Proposed change: keep the last approved allocation effective while a replacement correction is pending or rejected. The current service overwrites the same attribution row on submission; review this explicitly so a proposal cannot temporarily remove an approved amount from reports.
- Approved assignments feed reporting through a single effective-attribution resolver. Pending/rejected proposals must not become payable income.
- Separate attribution from payout posting: a tip correction must not change payroll merely because it was categorized or approved. Decide whether it is report-only or eligible for an explicit draft-payroll adjustment.
- For payroll adjustments, store an attribution revision reference and posting ID with a uniqueness constraint, preventing duplicate payment on repeated approvals/imports. A correction after payment should produce a reviewed delta/reversal in an open run, never rewrite finalized/paid history.
- Start with manual review of suggestions. Consider configurable automatic approval only for verified, unique source-ID matches after a dry-run audit; keep an owner kill switch and human decisions protected.

## Implementation order and acceptance

1. Read-only data audit: source-channel conflicts, duplicated order/show associations, explicit livestream coverage, current statuses, missing/multiple primaries, and the consumers of approved allocations. Use existing audit commands; summarize counts without exposing raw customer information.
2. Repair/import provenance: distinguish original source fields from importer context; retain source account, payload evidence and importer version. Review historical channel mapping; do not repair it by majority voting across contaminated joins.
3. Harden approval versioning/effective allocations and authorization. Test pending/rejected replacement corrections, concurrent reviews, self-approval rejection and protection of manual decisions.
4. Finish the inbox/evidence detail UI, manual destinations and unassigned alerts. Batch actions only for independently verified matches with an explicit preview.
5. Integrate effective assignments into tip/business reports; reconcile every signed event exactly once. Compare total ledger value = assigned + unassigned, accounting for excluded statuses explicitly.
6. Add optional payroll posting with idempotency, correction deltas and locked-history tests. No automatic production backfill until the dry-run totals and channel mapping are reviewed.

Critical fixtures: conflicting livestream IDs; reused listing; combined shipment; same order imported under many shows; sale+tip+refund on one order; cross-channel same order text; settlement after midnight; duplicate import; missing/multiple primaries; changed business; pending replacement of approved allocation; repeated approvals; and correction after a paid run.

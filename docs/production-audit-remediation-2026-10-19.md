# Production audit remediation — 2026-10-19

Base revision: a56496f668515d6e5faf33262030cbe2caafcb04.

## Implemented; full CI must pass before deployment

- A01: report ownership checked on every entry lookup, including direct URLs and Livewire actions.
- A02: item/detail/draft writes reject approved, locked, expired, or already submitted reports; edit-window reads no longer mutate the submission timestamp.
- A03/A08: shared individual/global location policy covers stock totals, report picking/posting, movement queries and location names. Limited viewers do not receive business-wide receipt/lot analyses.
- A04: report estimates and profit-share payroll use completed earnings, falling back to estimated earnings. Sales never substitute for missing earnings. Paid/finalized payout protection and saved rates remain in place.
- A05: overview links preserve exact dates; custom range comparisons and navigation preserve range length.
- A06: known zero/negative earnings no longer count as missing earnings.
- A07: dashboard review count uses the submitted/pending-approval workflow status.
- A09: pay-run readiness requires hours worked rather than broadcast duration, and missing share earnings block sign-off.
- A10: feedback harness follows the Livewire response callback, delayed indicator and success payload; server-error coverage added.
- A11: deployment depends on complete SQLite/MySQL CI and frontend checks, serializes deployments, and deploys only the validated commit if it is still the branch head.
- A14: streamer/settings changes now invalidate calculated pay-run readiness.
- A17: report posting serializes retries inside a transaction; stock changes and deducted quantity commit together. Sequential-retry and revision-removal regressions added. Submission, posting and reversal writes are transactional; revisions return posted stock before changing line quantities. Actual concurrent MySQL execution remains a CI/runtime verification item.

## Validation in this workspace

Passed: production frontend build, PHP syntax checks, Blade template compilation, shared UX/confirmation/action-feedback JavaScript checks, five scan-range checks, and git diff whitespace checks.

PHP runtime was provisioned during remediation. Focused runs passed: 106 tests / 308 assertions covering the changed domains and inbox/retry paths; a separate 200-test run / 934 assertions including Reliability; and 21 tests / 47 assertions after the final basket/revision/dashboard fixes. These runs overlap and must not be added into a unique test count.

The first complete CI attempt failed. Most SQLite failures cascaded from an obsolete ChannelContext::clear() call in FulfillmentCenterTest teardown; that call is corrected. A dashboard widget missing optional detail data and a MySQL-only monthly aggregate are corrected too. Other unrelated full-suite failures remain under investigation; passing focused checks is not a complete deployment pass. Deployment stays gated on the complete SQLite/MySQL CI suite. Production-only export reconciliation and restore verification remain open below.

## Audit items still requiring external evidence

- A12: reconcile tips against the actual Whatnot earnings export before changing whether tips are additive. No speculative change to financial history.
- A13: validate partial settlements against exports; current UI already describes completed earnings as “Settled so far.” A captured zero does not by itself prove that settlement is finished.
- A15/A16: added ownership/location regression coverage; a complete signed-in role/business/channel/export matrix still needs runtime execution. No blanket access or business assignment changes.
- A32: reconcile per-channel show IDs, counts, timestamps and earnings against selected production exports. No exports are available in this workspace.
- A34: restore a recent production backup into an isolated database/storage environment and record recovery time and integrity checks. No production backup or host session is available here. Backup creation alone is not restore verification.

No production records were edited, compensation rates changed, or paid history rewritten during source remediation. These verification items must stay open until evidence is recorded.


## Remaining cleanup batch

- Restored the missing all-channel import entry point used by three screens, retaining per-channel errors instead of reporting a partial import as a success.
- Restored a per-show order-count guard so a lost Seller Hub filter cannot attach a channel-wide scrape to one show.
- Fixed role visibility and read-only enforcement, hidden payroll navigation, fulfillment-admin identity/help, and explicit per-role overrides. Older admin navigation defaults remain available unless explicitly hidden.
- Applied the inventory table query to catalogue cards so location, own-stock, and other filters agree across views. Scoped vendor access to staff or an explicit role grant. Fixed SQLite record binding for aggregated inventory movement detail links.
- Kept approved/finalized payroll immutable while allowing draft calculations and loan withholding; payroll previews now calculate without writing historical payouts. The report editor now uses earnings and the shared profit-share formula, including unknown earnings and confirmed losses.
- Prevented submitted/approved monthly packets from being recalculated by a dashboard read and restored service initialization on Livewire updates.
- Replaced raw SQLite file copies with consistent snapshots, integrity checks and collision-safe filenames. An isolated WAL-data restore and checksum regression passes; this does not verify an actual production MySQL backup.
- Made browser/pipeline lock waits use monotonic time and retain owner metadata when a contender releases its own lock. Renamed the duplicate legacy refresh command.
- Historical backfills distinguish missing analytics from confirmed zero/negative earnings. Manifest parsing rejects partial-page failures; delayed jobs remain tracked and report their status without launching duplicate work.
- Updated regression coverage to exercise current report tabs, inventory cards, compensation overrides and bounded reporting schedules. Tests reject undeclared outbound HTTP requests. Full SQLite/MySQL CI remains required for deployment.

Validation is recorded in the associated commit and CI run; no focused test total should be treated as a complete suite result. Export reconciliation, live role/channel checks and a production-backup restore remain open as listed above.

MySQL validation also exposed a stocktake event absent from its movement enum, case-insensitive matching in the role-case migration, and hardcoded creator IDs in imports/demo fixtures. The cleanup appends the stocktake enum value without narrowing history, matches role names by exact bytes on MySQL, uses actual or system actors, and runs the frontend build in both database jobs. Backup tests restore the original database connection before transactional teardown; the help-content scan no longer depends on a libc-specific glob flag.

The final MySQL pass isolated four additional cases: product activity queried an obsolete status column, a legacy monthly packet queried a nonexistent surcharge amount, one orphan-report test used a SQLite-only pragma, and the MySQL units column could not preserve refunded net counts. Queries and fixtures are corrected; a widening migration preserves negative net units and all former unsigned values. Report/monthly share estimates now use effective configured percentages and the same earnings-based working. Pending earnings are identified and block packet finalization; submitted/approved packets remain unchanged.

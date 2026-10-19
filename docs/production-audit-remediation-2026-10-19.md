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

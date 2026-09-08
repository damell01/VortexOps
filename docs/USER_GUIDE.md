# VortexOps User Guide

_Last reviewed: September 7, 2026_

VortexOps is organized around one operating flow:

**Shows → Streamer Report → Admin Review → Fulfillment → Payroll Ready → Pay Run → Paid**

The main screens are designed around the same rule: read the current stage, read the blocker or next action, then use the primary action instead of hunting through tables.

## Handbook

Open **Handbook** at the top of the sidebar for module-by-module instructions. The handbook now includes:

- Inventory
- Shows & Streams
- Fulfillment
- Payroll & Pay Runs

Inventory includes the detailed photographed walkthroughs and printable handbook. The operational handbooks for Shows, Fulfillment, and Payroll now describe the current command-center and workspace flows so the instructions match the UI introduced in September 2026.

---

## Shows

### Shows Command Center

Use **Shows** to find the next show that needs work. The card layout is the main workflow surface; the advanced table remains available below it for admin filtering and bulk work.

Each show card emphasizes:

- Current workflow stage
- Show and streamer/channel
- Current blocker or state
- Progress
- Primary next action

### Show Workspace

Open a show to see its full workflow in one place:

**Show → Streamer Report → Admin Review → Fulfillment → Payroll Review → Payroll Ready → Pay Run → Paid**

The top **Next Action** panel is the safest place to start. The workspace keeps the four main handoffs together:

- Streamer Report
- Fulfillment
- Inventory / COGS
- Payroll

Show financials summarize gross sales, Whatnot net, tips, COGS, payroll, and show net. Secondary sync/show metadata is collapsed so it does not compete with the workflow.

---

## Streamer Report & Admin Review

The report uses a three-step flow:

1. **Items** — confirm products and quantities sold.
2. **Details** — complete required stream and financial information.
3. **Review & Submit** — verify the summary and send it to admin review.

The report status is intentionally simple:

- Draft
- Awaiting Review
- Changes Requested
- Approved

Admins use the same workspace. The final step becomes **Review & Approve**. Verify the item list, product cost, hours, and stream details before approving.

Use **Reject & Return** only when something is actually wrong. Use **Reopen for Editing** when a correct report simply needs its edit window restored.

---

## Fulfillment

### Fulfillment Workspace

The Fulfillment page is card-first and organized around operational stages:

- Needs Attention
- Ready to Pack
- Packing
- Seal Boxes
- Completed

Use the primary action on a show card to enter the packing flow.

### Packing Workstation

The packing screen keeps the active box and scanner at the top while the packing list stays directly underneath.

Normal flow:

1. Open the assigned show.
2. Pack or scan each line.
3. Build boxes from Whatnot shipment data.
4. Verify box contents.
5. Print the internal 4×6 label and verification QR.
6. Seal the box.
7. Use **Show Complete** only after all completion checks pass.

A show cannot complete while units remain, fulfillment issues are open, physical items have no package, or a package is still unsealed.

---

## Payroll

### Payroll Command Center

Payroll is organized into four workflow buckets:

- Needs Attention
- Payroll Ready
- In Pay Run
- Paid

Each show card includes its key financial inputs, show net, blocker/current state, and next action. Fix source issues before trying to move a blocked show forward.

The top-level KPIs focus on:

- Payroll Total
- People
- Ready / In Run / Paid
- Blocked

The **Run Readiness** panel shows what must be fixed before a draft can be finalized.

### Pay Run Workspace

A Pay Run follows:

**Review → Finalized → Submitted → Paid**

The workspace shows team-member totals first. Expand a person only when you need to inspect the payout lines that built their total.

Normal lifecycle:

1. Recalculate the draft when source shows change.
2. Resolve all readiness blockers.
3. **Finalize Pay Run** to lock payout amounts.
4. **Export ADP CSV**.
5. **Mark Submitted to ADP** after submission.
6. **Mark Paid** when payment is confirmed.

The Mock Pay Run simulator is secondary and writes no payroll data.

---

## Inventory

Inventory remains the most detailed handbook section and includes photographed walkthroughs of the real installed screens.

### Scanning modes

| Mode | What a scan does |
|---|---|
| **Look Up** | Reads only. Shows the item, cost, and location without changing stock. |
| **Add Stock** | Adds units to a selected location after quantity/location confirmation. |
| **Receive** | Receives units against a selected pallet/manifest line. |

A hardware scanner types into the code field and submits automatically. On mobile, use **Camera** and fill the frame with the barcode.

### Receiving a delivery

1. Create/stage the pallet with vendor and PO reference.
2. Add or import manifest lines.
3. Link every line to an inventory item.
4. Receive by scan or **Receive All** after a physical count.
5. Use **Mark Short** for missing product.
6. Complete receiving only when counts reconcile.

Receiving updates weighted average cost so downstream show COGS and profitability use what was actually paid.

### Product sheet import

**Inventory → Import Sheet** accepts `.xlsx`, `.xls`, and `.csv`. It previews creates, updates, matches, and questionable rows before writing anything. Existing costs are left alone unless overwrite is explicitly selected.

---

## Common Problems

### A show looks stuck
Open the **Show Workspace** and read the **Next Action** / blocker. The workflow state tells you which module owns the next step.

### A streamer cannot edit a report
The edit window may be closed. Admins can use **Reopen for Editing** without rejecting a correct report.

### Fulfillment will not complete
Check pending units, open issues, missing packages, and unsealed boxes.

### Finalize Pay Run is unavailable
Open **Readiness**. Draft runs cannot finalize until all blockers are cleared.

### An item is not found when scanning
The barcode is not attached to an inventory item. Find the item and attach/replace its barcode, or link it from the receiving flow.

### A location dropdown is empty
Check the location type. Different operations intentionally expose only compatible location types.

---

## Screenshots and documentation freshness

Inventory handbook screenshots are stored in `public/guide/manual/` and are referenced directly from `App\Support\InventoryManual`. Tests verify that Inventory handbook steps do not reference missing images and that screenshots are not left orphaned.

Shows, Fulfillment, and Payroll documentation was rewritten against the current September 2026 command-center/workspace UI. Their handbook entries intentionally do not reuse older screenshots from the pre-command-center pages; screenshots should only be added after they are captured from the current authenticated installation.

This avoids the more dangerous failure mode of a handbook showing a screenshot of a screen that no longer exists.

---

## Getting Help

- **Handbook** — module-by-module operating instructions at the top of the sidebar.
- **Next Action panels** — use these first on Show, Fulfillment, Payroll, and Pay Run workspaces.
- **Empty/error states** — follow the specific explanation on the screen instead of forcing the workflow forward.
- **Feedback / ticket** — use the app feedback path when a screen does not match the handbook.

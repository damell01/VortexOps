# VortexOps User Guide

_Last reviewed: September 8, 2026_

VortexOps is organized around one operating flow:

**Shows → Streamer Report → Admin Review → Fulfillment → Payroll Ready → Pay Run → Paid**

The main screens are designed around the same rule: read the current stage, read the blocker or next action, then use the primary action instead of hunting through tables.

## Handbook

Open **Handbook** at the top of the sidebar for module-by-module instructions. The handbook includes:

- Inventory
- Shows & Streams
- Fulfillment
- Payroll & Pay Runs

The operational handbook screenshots are generated from the current application so the screenshots and instructions stay aligned as the UI changes.

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

### Operational vs. Historical shows

Not every Whatnot show in the database is active work. Older scraper/backfill records are useful for analytics and scraper history but should not create fake streamer-report, fulfillment, or payroll tasks.

- **Operational** shows participate in the live workflow.
- **Historical** shows remain searchable/reportable but create no operational blockers.
- Older Whatnot imports with no streamer report are automatically classified as historical during the September 2026 migration.
- New Whatnot shows discovered more than 14 days in the past are treated as historical by default.
- An admin can open a show and use **More → Mark as Historical** or **Restore to Workflow** when an exception needs to be changed manually.

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

### Handoff to Fulfillment

Approval makes the streamer-logged item list eligible for fulfillment. The show is then assigned to one or more fulfillment team members.

- Admins, owners, and fulfillment admins can see all active fulfillment work and change assignments.
- A regular fulfillment team member sees only the approved shows assigned to them.
- A show with an approved report but no fulfillment assignment appears to admins as **Needs Assignment**.

---

## Fulfillment

### Fulfillment Center

The Fulfillment Center is based on the **streamer report**, not Whatnot buyer or shipment grouping. The streamer has already recorded the products and quantities that physically need to be accounted for.

The main stages are:

- Needs Assignment
- Ready to Pack
- Packing
- Issues
- Ready to Complete
- Completed

The primary cards show the information a fulfillment member can act on immediately:

- Streamer
- Assigned fulfillment member
- Item-line count
- Total streamer-logged units
- Units remaining
- Packing progress
- Issues

Buyer names and Whatnot shipment counts are intentionally removed from the primary queue.

### Packing Workstation

Normal flow:

1. Open the assigned show.
2. Work from the **Streamer Packing List**.
3. Scan a barcode/SKU or use **+1** / **Pack Remaining** for each streamer-logged item line.
4. Use **Flag Issue** when a physical item cannot be accounted for.
5. Optionally track physical boxes in VortexOps when box labels or verification are useful.
6. Use **Complete Fulfillment** after every logged unit is accounted for and issues are resolved.

A VortexOps box is **not required** to pack an item or complete a show. If someone deliberately creates a tracked box, that box must be sealed before fulfillment can be completed.

Whatnot shipment references remain available in a collapsed optional section. They are reference data only; buyer grouping and Whatnot's item grouping do not change the streamer packing list.

---

## Payroll

### Payroll Command Center

Payroll is organized into four workflow buckets:

- Needs Attention
- Payroll Ready
- In Pay Run
- Paid

Historical/non-operational shows are excluded from current payroll blockers and current-week operational calculations.

Each show card includes its key financial inputs, show net, blocker/current state, and next action. Fix source issues before trying to move a blocked show forward.

The top-level KPIs focus on:

- Payroll Total
- People
- Ready / In Run / Paid
- Blocked

The **Run Readiness** panel shows what must be fixed before a draft can be finalized.

### Mock Pay Run

**Mock Pay Run** is available directly in the Payroll page header. It jumps to the read-only Payroll Simulator on the same page.

The simulator uses current catalog/product costs but does not create, modify, finalize, export, or pay a real payroll run.

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

### An old show says a report or fulfillment work is required
Open the show as an admin and use **More → Mark as Historical**. Historical shows keep their imported analytics and scraper data without participating in the live workflow.

### A show is missing from a fulfillment member's queue
Confirm the streamer report is approved, it contains logged items, and that fulfillment member is assigned to the show. Admins can still see the complete active fulfillment queue.

### A show looks stuck
Open the **Show Workspace** and read the **Next Action** / blocker. The workflow state tells you which module owns the next step.

### A streamer cannot edit a report
The edit window may be closed. Admins can use **Reopen for Editing** without rejecting a correct report.

### Fulfillment will not complete
Check units remaining, open packing issues, and any optional tracked boxes that were created but not sealed. A box is not required when no box was created.

### Finalize Pay Run is unavailable
Open **Readiness**. Draft runs cannot finalize until all blockers are cleared.

### An item is not found when scanning
The barcode is not attached to an inventory item. Find the item and attach/replace its barcode, or link it from the receiving flow.

### A location dropdown is empty
Check the location type. Different operations intentionally expose only compatible location types.

---

## Screenshots and documentation freshness

Operational screenshots are stored in `public/guide/manual/`. Shows, Fulfillment, Admin Review, Payroll, and Pay Run screenshots are regenerated by the handbook Playwright workflow when these screens change. The same images are referenced by the in-app Handbook and README current-operations gallery.

This prevents the handbook from quietly drifting back to screenshots of old versions of the application.

---

## Getting Help

- **Handbook** — module-by-module operating instructions at the top of the sidebar.
- **Next Action panels** — use these first on Show, Fulfillment, Payroll, and Pay Run workspaces.
- **Empty/error states** — follow the specific explanation on the screen instead of forcing the workflow forward.
- **Feedback / ticket** — use the app feedback path when a screen does not match the handbook.

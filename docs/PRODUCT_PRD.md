# Vortex Breaks Product Reference

This document is the business and workflow reference for the software.

Use it for:

- understanding what the app does at a product level
- documenting which manual processes or spreadsheets were replaced
- tracking future requirements from the client
- updating flows without digging through code

This is intentionally different from [TECHNICAL.md](./TECHNICAL.md):

- `PRODUCT_PRD.md` = what the business needs, how users work, and why the system exists
- `TECHNICAL.md` = how the code, data, and services are implemented

---

## 1. Product summary

VortexOps is the internal operations system for **Vortex Breaks**, a Whatnot-based sports card break business.

Its job is to replace scattered manual tracking with one system for:

- inventory visibility
- show intake and review
- deduction approval
- streamer payout calculation
- review / annotation workflows
- admin settings and auditability

The app is designed for an operations team that needs strong review controls. It is **not** meant to be a fully automated “hands off” system.

Core principle:

- inventory changes that affect financial reconciliation should be reviewable and auditable

---

## 2. Business problems it solves

Before software like this, businesses in this space usually end up with some combination of:

- inventory spreadsheets
- streamer payout spreadsheets
- handwritten or ad-hoc show notes
- Slack / text / email threads for approvals
- manual deduction tracking after each show
- no reliable source of truth for stock by location
- no clean audit log for what changed and why

VortexOps replaces that with a single operational workspace.

---

## 3. What this system replaces

The exact names of the old spreadsheets may change over time, but functionally this app is replacing these categories:

### Replaced spreadsheet / manual process map

| Old process / sheet | What people were tracking manually | What now handles it |
|---|---|---|
| Inventory master sheet | SKU, item name, category, unit cost, reorder level | Inventory Items |
| Stock by location sheet | Quantity per location / streamer | Inventory Stock Levels |
| Inventory movement log | Transfers, adjustments, damage, returns | Inventory Movements |
| Show tracking sheet | Show title, date, units sold, revenue, net, tips, status | Shows |
| Deduction approval worksheet | Which items should be deducted after a show | Deduction Requests |
| Streamer payout calculator | Show-level payout calculations by streamer | Payouts |
| Weekly payout batch sheet | Grouping payouts into payroll windows | Weekly Payout Batches |
| Review / QA screenshot markup | Screenshot feedback and issue notes | Review Sessions + Review Items |
| Settings checklist / admin notes | Feature toggles, branding, notification rules | App Settings |

### Things still likely outside this app

At least today, these may still live elsewhere:

- accounting / bookkeeping system
- payroll provider submission
- Whatnot scraping source or external export source
- internal chat and communication
- customer support platform

Those can be documented and integrated later.

---

## 4. Primary user roles

### Admin / owner

Uses the system to:

- manage settings and branding
- control who has access
- review operations metrics
- monitor auditability and workflow completion

### Operations manager / inventory manager

Uses the system to:

- manage inventory and stock locations
- review shows
- approve or reject deductions
- investigate low stock or damaged items

### Stream operations / reconciliation staff

Uses the system to:

- prepare show data
- validate AI suggestions
- complete post-show reconciliation
- confirm payout outcomes

### Streamer

May use data from the system indirectly or in limited workflows:

- assigned inventory
- show association
- payout calculation visibility
- reconciliation notifications

### Internal reviewers / project stakeholders

Uses review mode to:

- annotate screens
- leave implementation notes
- capture bugs / suggestions / questions

---

## 5. Main modules and what they do

### Inventory

Purpose:

- maintain the product catalog
- track stock by location
- log all stock movements

Main concepts:

- items
- locations
- stock records
- movement history
- reorder thresholds

### Shows

Purpose:

- serve as the operational record of a live Whatnot show
- connect financial outcome to inventory deductions and payouts

Main concepts:

- title
- channel
- date and duration
- gross revenue
- Whatnot net
- tips
- assigned streamers
- workflow status

### Deduction Requests

Purpose:

- turn a show into a reviewable list of inventory deductions
- give ops a checkpoint before inventory is mutated

Main concepts:

- suggested lines
- approved quantity
- confidence level
- ops notes
- approve / reject lifecycle

### Payouts

Purpose:

- calculate what each streamer should be paid for a show
- support batching into payroll windows

Main concepts:

- payout type
- calculated payout
- loan repayment offsets
- batch membership
- payout status

### Review & Feedback

Purpose:

- support product review, bug capture, and annotated feedback directly on pages

Main concepts:

- review session
- review item
- screenshot / annotation
- comment
- status lifecycle

### Settings / Admin

Purpose:

- control branding, modules, AI settings, notifications, and maintenance actions

---

## 6. Core workflows

### Workflow A: inventory management

1. Ops creates or updates inventory items.
2. Ops receives inbound inventory into a receiving location as a pallet, case, box, or loose-unit container.
3. Cost details can be attached at intake, including seller cost, shipping, and other per-unit fees.
4. If needed, ops breaks a parent container down into child cases or smaller containers while preserving lineage.
5. Ops puts active child containers away into their destination inventory locations.
6. Inventory is moved, adjusted, returned, or marked damaged as ongoing operations continue.
7. Every stock mutation is logged in movement history.
8. Low-stock rules can trigger notifications.

### Workflow B: show reconciliation

1. A show is created manually or imported.
2. Streamers are assigned.
3. Financial values are entered or confirmed.
4. AI can suggest matching inventory deductions.
5. Ops reviews the deduction request.
6. Ops approves or rejects.
7. On approval:
   - inventory is deducted
   - movement logs are created
   - the show becomes reconciled
   - payouts are calculated

### Workflow C: payout batching

1. Draft payouts are created from reconciled shows.
2. Ops creates a weekly payout batch.
3. Eligible payouts are grouped into that batch.
4. Ops finalizes and marks the batch through its lifecycle.

### Workflow D: review / QA feedback

1. User enters review mode.
2. User captures a screen annotation or text-only note.
3. Note is saved into a review session.
4. Team reviews the issue in Project Hub / review resources.
5. Item status is updated as work progresses.

---

## 7. Guardrails and business rules

These are important product rules, not just technical details:

- inventory deductions should not silently happen without review
- stock movements need an audit trail
- payouts should be calculated from show + streamer rules, not hand-entered ad hoc
- review items should preserve context like page URL, comments, and annotations
- feature modules may be hidden, but disabled modules should fail safely

Potential future rule clarifications to gather from the client:

- when manual deductions are allowed
- who can override payout outputs
- whether streamers should see their own payout detail directly
- whether closed review sessions are immutable

---

## 8. Known current workflow assumptions

These are good topics to confirm with the client later:

- a show becomes the anchor object for reconciliation
- one show can involve multiple streamers
- AI is assistive, not authoritative
- the system is internal and single-tenant
- payroll approval still likely has an external final step

---

## 9. Questions to collect from the client later

When you get more business info, update this section first.

### Operations questions

- What exact spreadsheets are still being used today?
- Which columns from those sheets are still important?
- Which steps are still happening outside the app?
- What status changes are considered “done” by the team?

### Inventory questions

- Are there any location types not yet modeled?
- Are there pack / box / case conversions that need support?
- Are partial deductions or substitutions common?
- Does inventory need a hierarchy like pallet -> case -> unit or pallet -> case -> location assignment?
- When cases are broken apart, should the system retain lineage back to the original pallet or purchase lot?
- Will scanner workflows eventually be used only for receiving, or also for transfers, deductions, counts, and picks?
- Should costing preserve purchase-specific detail when the same SKU is bought on different invoices at different prices?

### Show workflow questions

- Which show fields are mandatory before reconciliation?
- What exceptions happen often enough to deserve their own UI?
- Does each show always map to one deduction request pattern?

### Payout questions

- What external payroll export is the real final destination?
- Are taxes / reimbursements / bonuses tracked elsewhere?
- Do batches need approvals from multiple people?

### Review / QA questions

- Should clients ever see review sessions directly?
- Do review items need tags, due dates, or owners?
- Should review mode support video capture or only screenshots?

---

## 10. Future enhancements backlog

These are product-level ideas, not engineering tasks yet:

- import legacy spreadsheet data into normalized models
- export payroll-ready files directly for the payroll provider
- richer dashboard KPI definitions by role
- stronger exception handling for unusual show reconciliation cases
- saved operational reports replacing remaining spreadsheet summaries
- better review session templates by project phase
- inventory hierarchy support for pallet / case / unit lineage
- scanner-assisted inventory receiving, transfer, and count workflows
- landed-cost tracking including seller cost, shipping fees, and total acquisition cost
- average-cost and lot-cost reporting when the same SKU is purchased at different prices
- later-phase employee time tracking with clock-in / clock-out

### Current roadmap notes from latest client direction

These notes should guide future planning, but they are not immediate build work:

- Time tracking is later and not part of the current implementation window.
- Inventory now has a first-pass pallet -> case -> location workflow in the admin for receiving, breakdown, and putaway.
- Scanner functionality is still planned for later inventory work.
- Inventory costing should eventually support:
  - seller cost
  - shipping fees
  - total landed cost
  - average price paid across different invoices for the same item
  - purchase-lot-aware cost visibility

### Recommended future inventory architecture direction

When this work starts, the safest product direction is likely:

- keep the existing item catalog as the SKU definition layer
- add receipt or lot records for purchase-specific costs
- add optional container records for pallet / case relationships
- preserve movement history when inventory is broken down from parent containers into destination locations
- calculate reporting values from lot-level data rather than overwriting one static unit cost

---

## 11. How to update this document

When new business info comes in:

1. update **What this system replaces**
2. update **Core workflows**
3. add new answers under **Questions to collect from the client later**
4. add new product ideas under **Future enhancements backlog**
5. if a business rule changed, mirror that change in [TECHNICAL.md](./TECHNICAL.md) only after the product rule is clear

This keeps the business truth separate from the implementation details.

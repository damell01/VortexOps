# VortexOps

Internal operations platform for **Vortex Breaks** — a Whatnot-based sports card break business.

Built with **Laravel 13** + **Filament v5**. Phases 1–3 complete: inventory foundation, show tracking, AI-assisted deduction, streamer payouts, and client feedback tooling.

---

## Walkthrough Video

Full end-to-end walkthrough at 1440 × 900 — every workflow, every page, every clickable action. Captured automatically by the Playwright tour.

<video src="public/media/vortexops-walkthrough.mp4" controls width="100%"></video>

> **Can't play inline?** [Download vortexops-walkthrough.mp4](public/media/vortexops-walkthrough.mp4)

---

## Screenshots

Auto-captured by the Playwright UI tour and stored in [`tests/Browser/screenshots/`](tests/Browser/screenshots/). Each row shows the **desktop** view (1440 × 900) alongside the **mobile** view (390 × 844). Regenerate any time with `npx playwright test`.

---

### Login

| Desktop | Mobile |
|---|---|
| ![Login page](tests/Browser/screenshots/desktop/01-login-empty.png) | ![Login page](tests/Browser/screenshots/mobile/01-login-empty.png) |
| ![Login filled](tests/Browser/screenshots/desktop/02-login-filled.png) | ![Login filled](tests/Browser/screenshots/mobile/02-login-filled.png) |

---

### Dashboard

| Desktop | Mobile |
|---|---|
| ![Dashboard](tests/Browser/screenshots/desktop/03-dashboard.png) | ![Dashboard](tests/Browser/screenshots/mobile/03-dashboard.png) |
| ![Stat card hover](tests/Browser/screenshots/desktop/04-dashboard-stat-hover.png) | ![Stat card hover](tests/Browser/screenshots/mobile/04-dashboard-stat-hover.png) |
| ![Dark mode](tests/Browser/screenshots/desktop/05-dashboard-dark.png) | ![Dark mode](tests/Browser/screenshots/mobile/05-dashboard-dark.png) |
| ![Dashboard full scroll](tests/Browser/screenshots/desktop/45-dashboard-final.png) | ![Dashboard full scroll](tests/Browser/screenshots/mobile/45-dashboard-final.png) |

The dashboard includes a **Shows Calendar Widget** — a full-width monthly calendar with colour-coded shows by status (draft/pending review/mapping/pending approval/reconciled/closed), month navigation, and a legend. Hovering a show chip displays the title inline.

---

### Inventory — Items

| Desktop | Mobile |
|---|---|
| ![Inventory items list](tests/Browser/screenshots/desktop/06-inventory-items-list.png) | ![Inventory items list](tests/Browser/screenshots/mobile/06-inventory-items-list.png) |
| ![Inventory search](tests/Browser/screenshots/desktop/07-inventory-search.png) | ![Inventory search](tests/Browser/screenshots/mobile/07-inventory-search.png) |
| ![Inventory item detail](tests/Browser/screenshots/desktop/08-inventory-item-detail.png) | ![Inventory item detail](tests/Browser/screenshots/mobile/08-inventory-item-detail.png) |
| ![Inventory item detail full](tests/Browser/screenshots/desktop/09-inventory-item-detail-full.png) | ![Inventory item detail full](tests/Browser/screenshots/mobile/09-inventory-item-detail-full.png) |

---

### Inventory — Scanner

The **Inventory Scanner** page (`/admin/inventory-scanner`) is purpose-built for warehouse use. Scan any barcode with a Bluetooth or USB scanner, or use the camera button on supported browsers (Chrome, Edge, Android) — it uses the native `BarcodeDetector` API with a ZXing WebAssembly polyfill for iOS Safari and Firefox.

| Desktop | Mobile |
|---|---|
| ![Scanner empty](tests/Browser/screenshots/desktop/35b-inventory-scanner.png) | ![Scanner empty](tests/Browser/screenshots/mobile/35b-inventory-scanner.png) |
| ![Scanner typed](tests/Browser/screenshots/desktop/35c-inventory-scanner-typed.png) | ![Scanner typed](tests/Browser/screenshots/mobile/35c-inventory-scanner-typed.png) |
| ![Scanner result](tests/Browser/screenshots/desktop/35d-inventory-scanner-result.png) | ![Scanner result](tests/Browser/screenshots/mobile/35d-inventory-scanner-result.png) |
| ![Scanner result full](tests/Browser/screenshots/desktop/35e-inventory-scanner-result-full.png) | ![Scanner result full](tests/Browser/screenshots/mobile/35e-inventory-scanner-result-full.png) |

Look up any item by SKU or barcode. The result shows name, category, total units by location, average cost, reorder threshold with Low Stock badge, and last 10 movements. From the result, tap **Adjust Stock** to apply a quantity change (+/−) with an optional reason — the change is written as an `adjustment` movement immediately.

---

### Inventory — Locations, Movements & Stock

| Desktop | Mobile |
|---|---|
| ![Inventory locations](tests/Browser/screenshots/desktop/31-inventory-locations.png) | ![Inventory locations](tests/Browser/screenshots/mobile/31-inventory-locations.png) |
| ![Inventory movements](tests/Browser/screenshots/desktop/32-inventory-movements.png) | ![Inventory movements](tests/Browser/screenshots/mobile/32-inventory-movements.png) |
| ![Inventory stock](tests/Browser/screenshots/desktop/33-inventory-stock.png) | ![Inventory stock](tests/Browser/screenshots/mobile/33-inventory-stock.png) |

---

### Receiving — Pallets

| Desktop | Mobile |
|---|---|
| ![Pallets list](tests/Browser/screenshots/desktop/10-pallets-list.png) | ![Pallets list](tests/Browser/screenshots/mobile/10-pallets-list.png) |
| ![Pallet detail](tests/Browser/screenshots/desktop/11-pallet-detail.png) | ![Pallet detail](tests/Browser/screenshots/mobile/11-pallet-detail.png) |
| ![Pallet detail full](tests/Browser/screenshots/desktop/12-pallet-detail-full.png) | ![Pallet detail full](tests/Browser/screenshots/mobile/12-pallet-detail-full.png) |

---

### Shows

| Desktop | Mobile |
|---|---|
| ![Shows list](tests/Browser/screenshots/desktop/13-shows-list.png) | ![Shows list](tests/Browser/screenshots/mobile/13-shows-list.png) |
| ![Show detail](tests/Browser/screenshots/desktop/14-show-detail.png) | ![Show detail](tests/Browser/screenshots/mobile/14-show-detail.png) |
| ![Show detail full](tests/Browser/screenshots/desktop/15-show-detail-full.png) | ![Show detail full](tests/Browser/screenshots/mobile/15-show-detail-full.png) |
| ![Show action modal](tests/Browser/screenshots/desktop/16-show-action-modal.png) | ![Show action modal](tests/Browser/screenshots/mobile/16-show-action-modal.png) |
| ![Create show form](tests/Browser/screenshots/desktop/17-show-create-form.png) | ![Create show form](tests/Browser/screenshots/mobile/17-show-create-form.png) |
| ![Create show full page](tests/Browser/screenshots/desktop/18-show-create-form-full.png) | ![Create show full page](tests/Browser/screenshots/mobile/18-show-create-form-full.png) |
| ![Show ingestion logs](tests/Browser/screenshots/desktop/27-show-ingestion-logs.png) | ![Show ingestion logs](tests/Browser/screenshots/mobile/27-show-ingestion-logs.png) |

#### Advanced Filters — Shows

The Shows table supports a **QueryBuilder** panel with 7 constraint types. Combine rules with AND/OR logic, compare dates and revenue ranges, and filter by status, import source, or title keyword — all without leaving the table.

| Desktop | Mobile |
|---|---|
| ![Filter panel open](tests/Browser/screenshots/desktop/13b-shows-filter-panel.png) | ![Filter panel open](tests/Browser/screenshots/mobile/13b-shows-filter-panel.png) |
| ![Advanced filter rules](tests/Browser/screenshots/desktop/13c-shows-advanced-filter.png) | ![Advanced filter rules](tests/Browser/screenshots/mobile/13c-shows-advanced-filter.png) |

---

### Deduction Requests

| Desktop | Mobile |
|---|---|
| ![Deduction requests](tests/Browser/screenshots/desktop/19-deduction-requests.png) | ![Deduction requests](tests/Browser/screenshots/mobile/19-deduction-requests.png) |

---

### Payouts & Pay Runs

| Desktop | Mobile |
|---|---|
| ![Payouts list](tests/Browser/screenshots/desktop/20-payouts-list.png) | ![Payouts list](tests/Browser/screenshots/mobile/20-payouts-list.png) |
| ![Payout detail](tests/Browser/screenshots/desktop/21-payout-detail.png) | ![Payout detail](tests/Browser/screenshots/mobile/21-payout-detail.png) |
| ![Weekly pay runs](tests/Browser/screenshots/desktop/22-weekly-pay-runs.png) | ![Weekly pay runs](tests/Browser/screenshots/mobile/22-weekly-pay-runs.png) |

#### Payout Grouping & Summaries

The Payouts table supports **three grouping modes** via the Group select: by Streamer, by Pay Week, or by Status. Groups are collapsible. The table also shows **column totals** — a Sum summary row at the bottom for Gross Revenue and Total Payouts across the current filtered view.

| Desktop | Mobile |
|---|---|
| ![Group menu open](tests/Browser/screenshots/desktop/20b-payouts-group-menu.png) | ![Group menu open](tests/Browser/screenshots/mobile/20b-payouts-group-menu.png) |
| ![Grouped by streamer](tests/Browser/screenshots/desktop/20c-payouts-grouped-by-streamer.png) | ![Grouped by streamer](tests/Browser/screenshots/mobile/20c-payouts-grouped-by-streamer.png) |

---

### Streamers & Loans

| Desktop | Mobile |
|---|---|
| ![Streamers list](tests/Browser/screenshots/desktop/23-streamers-list.png) | ![Streamers list](tests/Browser/screenshots/mobile/23-streamers-list.png) |
| ![Streamer detail](tests/Browser/screenshots/desktop/24-streamer-detail.png) | ![Streamer detail](tests/Browser/screenshots/mobile/24-streamer-detail.png) |
| ![Streamer detail full](tests/Browser/screenshots/desktop/25-streamer-detail-full.png) | ![Streamer detail full](tests/Browser/screenshots/mobile/25-streamer-detail-full.png) |
| ![Streamer loans](tests/Browser/screenshots/desktop/26-streamer-loans-list.png) | ![Streamer loans](tests/Browser/screenshots/mobile/26-streamer-loans-list.png) |

---

### Reports

| Desktop | Mobile |
|---|---|
| ![Reports overview](tests/Browser/screenshots/desktop/28-reports.png) | ![Reports overview](tests/Browser/screenshots/mobile/28-reports.png) |
| ![Reports full scroll](tests/Browser/screenshots/desktop/29-reports-full.png) | ![Reports full scroll](tests/Browser/screenshots/mobile/29-reports-full.png) |

---

### Vendors & Whatnot Channels

| Desktop | Mobile |
|---|---|
| ![Vendors](tests/Browser/screenshots/desktop/34-vendors-list.png) | ![Vendors](tests/Browser/screenshots/mobile/34-vendors-list.png) |
| ![Whatnot channels](tests/Browser/screenshots/desktop/35-whatnot-channels.png) | ![Whatnot channels](tests/Browser/screenshots/mobile/35-whatnot-channels.png) |

---

### Log Viewer

The **Log Viewer** page (`/admin/log-viewer`) gives admins a real-time window into the Laravel application log without touching the server.

| Desktop | Mobile |
|---|---|
| ![Log viewer](tests/Browser/screenshots/desktop/35f-log-viewer.png) | ![Log viewer](tests/Browser/screenshots/mobile/35f-log-viewer.png) |
| ![Log viewer full](tests/Browser/screenshots/desktop/35g-log-viewer-full.png) | ![Log viewer full](tests/Browser/screenshots/mobile/35g-log-viewer-full.png) |
| ![Log viewer expanded entry](tests/Browser/screenshots/desktop/35h-log-viewer-expanded.png) | ![Log viewer expanded entry](tests/Browser/screenshots/mobile/35h-log-viewer-expanded.png) |

Features: select which log file to view, filter by level (debug/info/warning/error/critical), search by keyword, paginate entries (25/50/100), and expand any entry to reveal the full stack trace. Counts per level are shown as pills in the header. A "Clear Log" action wipes the selected file (owner only).

---

### AI Assistant Panel

| Desktop | Mobile |
|---|---|
| ![AI chat panel](tests/Browser/screenshots/desktop/36-ai-panel-closed.png) | ![AI chat panel](tests/Browser/screenshots/mobile/36-ai-panel-closed.png) |

---

### Settings

| Desktop | Mobile |
|---|---|
| ![Settings](tests/Browser/screenshots/desktop/41-settings.png) | ![Settings](tests/Browser/screenshots/mobile/41-settings.png) |
| ![Settings full scroll](tests/Browser/screenshots/desktop/42-settings-full.png) | ![Settings full scroll](tests/Browser/screenshots/mobile/42-settings-full.png) |

---

### Admin — Users & Activity Log

| Desktop | Mobile |
|---|---|
| ![Users](tests/Browser/screenshots/desktop/39-users-list.png) | ![Users](tests/Browser/screenshots/mobile/39-users-list.png) |
| ![Activity log](tests/Browser/screenshots/desktop/40-activity-log.png) | ![Activity log](tests/Browser/screenshots/mobile/40-activity-log.png) |

---

### Desktop — Navigation & Global Search

| User dropdown | Global search (⌘K) |
|---|---|
| ![Dropdown menu](tests/Browser/screenshots/desktop/43-dropdown-open.png) | ![Global search](tests/Browser/screenshots/desktop/44-global-search.png) |

---

### Mobile — Sidebar

![Mobile sidebar open](tests/Browser/screenshots/mobile/43-sidebar-open.png)

---

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Admin panel | Filament v5 + Livewire 3 |
| Database | SQLite (dev) / MySQL (prod) |
| Auth & Roles | Spatie Laravel Permission v7 |
| Audit log | Spatie Activitylog v5 |
| Queue | Laravel Queues (database driver) |
| AI | Ollama (local LLM, no external API) |

---

## Key design constraints

- **Single-tenant only** — not a SaaS platform
- **Inventory deductions never happen automatically** — every deduction requires explicit ops approval
- **Full audit trail** — every inventory change creates an immutable movement record
- **Whatnot channels are shared** — multiple streamers can work on the same channel
- **Payouts are calculated, not entered** — the payout engine derives amounts from show financials and streamer rate config

---

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Admin login: `admin@vortexbreaks.com` / `password`  
Dev (super admin): `dev@vortexbreaks.com` / `devpassword`

Demo data includes 3 streamers, 8 inventory items, stock across all locations, 3 shows at different stages (reconciled / pending approval / draft), deduction requests, payouts, and 2 weekly pay run batches.

To run the queue worker (required for AI mapping and low-stock notifications):

```bash
php artisan queue:work
```

To regenerate screenshots and the walkthrough video:

```bash
php artisan serve --port=8000 &
npx playwright test              # both desktop + mobile screenshots
npx playwright test --project=desktop  # desktop only (also writes video.webm)
# Convert to MP4:
ffmpeg -y -i tests/Browser/output/*/video.webm \
  -c:v libx264 -preset slow -crf 20 -movflags +faststart \
  public/media/vortexops-walkthrough.mp4
```

## Deployment

Production deployment assets:

- Docker image: `Dockerfile` + `docker-compose.yml`
- Production env: `.env.production.example`
- Ubuntu VPS installer: `deploy/install-vps.sh`

---

## Navigation groups

| Group | Resources |
|---|---|
| **Streams** | Shows, Deduction Requests |
| **Inventory** | Items, Locations, Stock Levels, Movement Log, **Inventory Scanner** |
| **Payouts & Pay Runs** | Payouts, Pay Runs (Weekly Batches) |
| **Operations** | Feedback Tickets |
| **AI** | AI Assistant, AI Logs |
| **Settings** | App Settings, Users, Activity Log, **Log Viewer** |

---

## Shows & the operational loop

The core workflow that ties everything together:

```
Create Show
    │
    ▼
pending_review ──► Assign streamers + enter financials
    │
    ▼
[Run AI Mapping] ──► Jobs: ParseShowTitle → MapShowInventory
    │
    ▼
mapping ──► AI reads show title + available inventory → creates DeductionRequest with suggested lines
    │
    ▼
pending_approval ──► Ops reviews/edits deduction lines in the approval UI
    │
    ├── Approve ──► inventory deducted, show → reconciled, payouts calculated
    └── Reject  ──► show returns to pending_review (retry loop)
```

### Show statuses

| Status | Meaning |
|---|---|
| `draft` | Just created; no streamers or financials yet |
| `pending_review` | Ready for ops to assign streamers and trigger AI mapping |
| `mapping` | AI job running — deduction lines being generated |
| `pending_approval` | Deduction request created; waiting for ops to approve or reject |
| `reconciled` | Approved and inventory deducted; payouts generated |
| `closed` | Manually closed without reconciliation |
| `cancelled` | Cancelled; no deductions |

---

## Deduction Requests

Each show generates one `DeductionRequest` (one per streamer at the time of AI mapping). The request contains one or more `DeductionRequestLine` records, each representing one inventory item to be deducted.

**Approval UI** (`/admin/deduction-requests/{id}`):
- Shows the full show summary (revenue, units sold, streamer)
- Displays AI mapping notes and confidence levels per line
- Ops can edit quantity approved, unit cost, and item/location per line
- Ops can add or remove lines manually
- Approve button persists all edits, then calls `InventoryService::deductStock()` for each approved line
- Reject button returns the show to `pending_review` and records the rejection reason

### Deduction line confidence levels

| Level | Meaning |
|---|---|
| `high` | AI is confident in the item match |
| `medium` | AI has a reasonable guess but needs review |
| `low` | AI is unsure; ops should verify or replace |
| `manual` | Line was added or overridden by ops |

---

## Payout engine

Payouts are calculated by `PayoutService::calculateForShow()` and triggered automatically after a deduction request is approved.

### Payout types (set per streamer)

| Type | Calculation |
|---|---|
| `profit_share` | `whatnot_net × (payout_percentage / 100)`, optionally + tips share |
| `package` | Fixed `package_rate`, optionally + tips share |
| `hourly` | `hourly_rate × (show_duration_minutes / 60)` |
| `flat_rate` | Fixed `package_rate` (no tips) |

Tips are divided equally among all streamers on the show when `include_tips = true`.

### Weekly pay runs (batches)

Ops creates a `WeeklyPayoutBatch` for a given Monday–Sunday range. All unbatched draft payouts for shows in that week are pulled into the batch. Finalizing a batch marks all included payouts as `approved`. Batch statuses: `draft → finalized → submitted_to_adp → paid`.

---

## Inventory

### Location types

| Type | Purpose |
|---|---|
| `main_storage` | Primary warehouse / storage |
| `streamer_inventory` | Stock assigned to a specific streamer |
| `returned` | Buyer returns staging area |
| `damaged` | Damaged / unsellable units |
| `fulfillment` | Outbound / shipping staging |

### Stock operations (per item, via action menu)

| Action | What it does |
|---|---|
| Add Stock | Adds units to a location. Logs `opening`, `adjustment`, or `return` movement. |
| Transfer Stock | Moves units between two locations. Debits source, credits destination. |
| Adjust Inventory | Sets an exact quantity. Computes delta and logs a signed `adjustment` movement. |
| Mark Damaged | Moves units from any location to the designated damaged location. Sends a danger notification. |
| Move to Returns | Moves units to the designated returns location. |

All operations are wrapped in database transactions. Insufficient stock throws `RuntimeException` before any mutation occurs.

### Low stock notifications

After every stock operation, `InventoryService` checks `item.totalQuantity() <= item.reorder_level`. When triggered, a queued `SendLowStockNotification` job sends a warning database notification to all users.

---

## Feedback system

A floating **"Feedback"** button sits in the bottom-right corner of every page. Clicking it:

1. **Captures a live screenshot** of the current page (via html2canvas) without the widget visible
2. Opens an **annotation canvas** with tools: freehand pen, rectangle, arrow, highlight — 6 colors + 3 line widths + undo
3. Prompts for **title, description, and priority** (Low / Medium / High)
4. Submits and stores the annotated screenshot + metadata as a **FeedbackTicket**

Tickets are managed under **Operations → Feedback** with full priority/status lifecycle:
`open → in_progress → resolved / closed` (re-open supported)

Admins can assign tickets, add internal notes, and view the annotated screenshot inline.

---

## AI (Ollama)

All AI runs locally via Ollama. No data leaves the server.

### AI services

| Service | What it does |
|---|---|
| `AiTitleParserService` | Parses a show title to suggest which streamer hosted it. Called by `ParseShowTitle` job. |
| `AiInventoryMapperService` | Given a show's title, units sold, and available inventory catalogue, returns a JSON mapping of which items were likely sold. Called by `MapShowInventory` job. |
| `OllamaService` | HTTP client wrapper. `chat()`, `json()`, `isAvailable()`, `availableModels()`. All calls are logged to `ai_logs`. |

### Queue jobs

| Job | Triggered by | On failure |
|---|---|---|
| `ParseShowTitle` | Show created with a non-null title | Logs error; show stays `pending_review` |
| `MapShowInventory` | "Run AI Mapping" action on show | Logs error; show returns to `pending_review` |
| `NotifyShowReady` | Show created | Sends database notification to all admins |
| `NotifyShowReconciled` | Deduction approved | Sends database notification to all admins |
| `SendLowStockNotification` | Any stock operation that drops below reorder level | Sends warning notification (queued, after commit) |

### AI floating panel

A sparkles button sits in the bottom-right corner of every admin page. Clicking it opens a chat panel that automatically loads context for the current page (inventory item, location, streamer, or dashboard overview).

### Settings

```
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
OLLAMA_TIMEOUT=60
```

Or configure via **Settings → AI Assistant**. Use the "Test Ollama" button to verify connectivity.

```bash
ollama serve
ollama pull llama3.2
```

---

## Roles & access

| Role | Access |
|---|---|
| `super_admin` | Everything, including role assignment. Dev use only. |
| `admin` | Full access to all resources, settings, and user management |
| `streamer` | Inventory items, their own locations + shared locations, movement log, their own payouts. No settings, no user management. |

Assign a streamer user to a **Streamer profile** via the linked profile field on the user form. Inventory locations then scope automatically to that streamer's own locations plus all shared (non-streamer-assigned) locations.

---

## Data model

```
WhatnotChannel
     │
     └──< Show >────────────────────────────< show_streamer >─────────< Streamer
              │                                                               │
              ├──< DeductionRequest >─────< DeductionRequestLine      InventoryLocation (streamer_id FK)
              │         │                       │          │                  │
              │   approved_by (User)     InventoryItem  Location        InventoryStock
              │                                                               │
              └──< Payout >──< WeeklyPayoutBatch                       InventoryItem
                    │
                 Streamer

InventoryMovement (inventory_item_id, from_location_id, to_location_id, quantity, type, created_by)
FeedbackTicket    (title, description, screenshot_path, page_url, status, priority, submitted_by, assigned_to)
Setting           (key / value — cached 1 hour)
AiLog             (action, prompt, response, latency_ms, success)
```

### Movement types

| Type | When created |
|---|---|
| `opening` | Initial stock entry |
| `transfer` | Stock moved between locations |
| `adjustment` | Quantity corrected to exact value |
| `sale_deduction` | Inventory deducted from an approved deduction request |
| `return` | Item returned to inventory |
| `damaged` | Item moved to damaged location |

---

## Project structure

```
app/
├── Filament/
│   ├── Pages/
│   │   ├── AppSettings.php              # branding, AI, notifications, maintenance actions
│   │   ├── AiAssistant.php             # full-screen AI chat page
│   │   ├── InventoryScanner.php        # barcode/SKU lookup + quick stock adjustment
│   │   └── LogViewer.php              # log file browser with level filter + search
│   ├── Resources/
│   │   ├── ShowResource.php            # show CRUD + AI mapping action + QueryBuilder filters
│   │   ├── DeductionRequestResource/
│   │   │   └── Pages/ViewDeductionRequest.php   # approval/reject UI
│   │   ├── FeedbackTicketResource/
│   │   │   └── Pages/ViewFeedbackTicket.php     # status lifecycle + admin notes
│   │   ├── InventoryItemResource.php   # 5 stock operation modals + QueryBuilder filters
│   │   ├── InventoryLocationResource.php
│   │   ├── InventoryMovementResource.php        # read-only audit log
│   │   ├── InventoryStockResource.php           # read-only stock view
│   │   ├── PayoutResource.php          # grouping (streamer/week/status) + summaries + QueryBuilder
│   │   ├── WeeklyPayoutBatchResource.php
│   │   ├── StreamerResource.php        # channel routing repeater (reorderable, cloneable, collapsible)
│   │   ├── WhatnotChannelResource.php
│   │   ├── UserResource.php
│   │   ├── ActivityLogResource.php              # Spatie activity log viewer
│   │   └── AiLogResource.php
│   └── Widgets/
│       ├── ShowsCalendarWidget.php      # monthly calendar with colour-coded shows by status
│       ├── InventoryOverviewWidget.php  # cached stat cards
│       ├── LowStockWidget.php
│       ├── RecentMovementsWidget.php
│       ├── InventoryByLocationWidget.php
│       └── ActiveStreamersWidget.php
├── Jobs/
│   ├── ParseShowTitle.php
│   ├── MapShowInventory.php
│   ├── NotifyShowReady.php
│   ├── NotifyShowReconciled.php
│   └── SendLowStockNotification.php    # queued, dispatched after commit
├── Livewire/
│   ├── AiChatPanel.php                 # floating AI chat sidebar
│   └── FeedbackWidget.php             # screenshot capture + annotation + submit
├── Models/
│   ├── Show.php · DeductionRequest.php · DeductionRequestLine.php
│   ├── InventoryItem.php · InventoryLocation.php · InventoryMovement.php · InventoryStock.php
│   ├── Streamer.php · WhatnotChannel.php
│   ├── Payout.php · WeeklyPayoutBatch.php
│   ├── FeedbackTicket.php
│   ├── User.php · Setting.php · AiLog.php
└── Services/
    ├── InventoryService.php             # all stock mutations, transactions
    ├── OllamaService.php               # Ollama HTTP client + AI log
    ├── PayoutService.php               # payout calculation + weekly batch creation
    ├── AiTitleParserService.php
    ├── AiInventoryMapperService.php
    ├── DeductionApprovalService.php    # approve + execute deductions
    └── DeductionRejectionService.php   # reject + return show to pending_review
```

---

## Development phases

| Phase | Scope | Status |
|---|---|---|
| **Phase 1** | Inventory & Product Cost Foundation — items, locations, stock levels, movement log, streamer profiles, Whatnot channels | ✅ Complete |
| **Phase 2** | Stream Tracking — show scheduling, status workflow, AI title parsing, show financials | ✅ Complete |
| **Phase 3** | Reconciliation & Deduction — AI inventory mapping, deduction approval workflow, payout calculation engine, weekly pay runs | ✅ Complete |
| **Phase 3.5** | Platform Polish — performance optimization, mobile-responsive tables, nav badges, filter caching, client feedback tooling | ✅ Complete |
| **Phase 3.6** | Scanner & Ops Tools — barcode scanner page (BT/USB/camera), log viewer, shows calendar widget, QueryBuilder advanced filters, payout grouping, table summaries, streamer repeater enhancements | ✅ Complete |
| **Phase 4** | Operational Reporting — P&L summaries, per-streamer profitability, COGS trends, show performance dashboards | Planned |
| **Phase 5** | Automation & Expansion — Whatnot API integration, automated show ingestion, advanced analytics, webhook alerts | Planned |

**Timeline notes:** Timelines vary depending on workflow discoveries, operational changes, review cycles, testing, platform limitations, client feedback, and evolving business requirements.

---

## Partnership & Pricing

**Prepared by DBell Creations for Vortex Breaks**

| | |
|---|---|
| **Project Initiation & Environment Setup** | **$1,000** one-time setup fee |
| **Monthly Partnership Retainer** | **$4,000 / mo** ongoing development & management |

### What the monthly retainer includes

| | |
|---|---|
| Ongoing platform development and feature enhancements | Hosting and infrastructure management |
| Workflow improvements and operational updates | Backups and platform monitoring |
| Inventory workflow development and reporting enhancements | Future operational enhancements and additions |
| Support and maintenance | Workflow optimization and operational consulting |
| Bug fixes and platform improvements | |

---

**DBell Creations**  
📞 (251) 406-2292 · ✉ dbellcreations@gmail.com · 🌐 www.dbellcreation.com

# VortexOps

Internal operations platform for **Vortex Breaks** — a Whatnot-based sports card break business.

Built with **Laravel 13** + **Filament v5**. Phases 1–3 complete: inventory foundation, show tracking, AI-assisted deduction, streamer payouts, and client feedback tooling.

---

## Walkthrough Video

Full end-to-end walkthrough at 1440 × 900 — every workflow, every page, every clickable action. Captured automatically by the Playwright tour.

<video src="public/media/vortexops-walkthrough.mp4" controls width="100%"></video>

> **Can't play inline?** [Download vortexops-walkthrough.mp4](public/media/vortexops-walkthrough.mp4)

---

## User Roles & Permissions

VortexOps has three distinct roles with scoped access:

### 1. Super Admin (`super_admin`)

**Access:** Everything, including role assignment and dangerous operations.  
**Use case:** Dev/operations team only. Don't assign to production users.

| Feature | Access |
|---------|--------|
| All resources (full CRUD) | ✅ |
| Settings & configuration | ✅ |
| User management & role assignment | ✅ |
| Activity log viewer | ✅ |
| Log viewer | ✅ |
| Whatnot sync dashboard | ✅ |
| Clear logs / dangerous actions | ✅ |

**Default test account:** `dev@vortexbreaks.com` / `devpassword`

---

### 2. Admin (`admin`)

**Access:** Full platform access, no role management.  
**Use case:** Operations team — they drive the business workflows.

| Feature | Access |
|---------|--------|
| Shows (create/edit/manage all) | ✅ |
| Streamers (view + edit profiles) | ✅ |
| Payouts (calculate + approve pay runs) | ✅ |
| Inventory (all operations + adjustments) | ✅ |
| Pallets & receiving (full workflow) | ✅ |
| Deduction requests (approve/reject) | ✅ |
| Whatnot channels + sync control | ✅ |
| User management | ✅ |
| Activity log viewer | ✅ |
| Log viewer | ✅ |
| Settings | Subset (can't disable modules) |

**Permissions:** `admin` role (Spatie Laravel Permission v7)

**Default test account:** `admin@vortexbreaks.com` / `password`

---

### 3. Streamer (`streamer`)

**Access:** Limited self-service view of their own data.  
**Use case:** Streamers log into the platform to see their own shows, payouts, and balances.

| Feature | Access |
|---------|--------|
| Shows (view own only, scoped to their shows) | ✅ |
| Add items to show logs (manual mapping) | ✅ |
| View own payouts | ✅ |
| View payout history | ✅ |
| Inventory items (read-only list) | ✅ |
| Inventory locations (own + shared only) | ✅ |
| Movement log (view only) | ✅ |
| Deduction requests (view own only) | ✅ |
| **Cannot:** Create shows, manage payouts, access admin settings, assign roles | ❌ |

**Setup:** Link a Streamer profile to a User record via the user form. Inventory scope applies automatically.

**Test account example:** `streamer@example.com` / `password` (must be linked to a Streamer profile)

---

## Screenshots & Role-Based Views

All screenshots are auto-captured by the **Playwright UI tour** (`tests/Browser/screenshot-tour.spec.ts`) and stored in [`tests/Browser/screenshots/`](tests/Browser/screenshots/). Each row shows the **desktop** view (1440 × 900) alongside the **mobile** view (390 × 844).

### Regenerate Screenshots

To capture fresh screenshots of all functionality across all user roles:

```bash
# Start the development server
php artisan serve --port=8000 &

# Clear caches
php artisan config:clear && php artisan cache:clear

# Run the Playwright tour (all roles, all pages, desktop + mobile, 14 test suites)
npx playwright test tests/Browser/screenshot-tour.spec.ts --project=chromium

# Results saved to tests/Browser/screenshots/{desktop,mobile}/
```

The tour tests 14 different scenarios:
1. **Authentication** — Login page empty/filled
2. **Owner Dashboard** — Full dashboard with stats and dark mode
3. **Shows Resource** — List, search, filters, detail view
4. **Streamers Resource** — List and detail view
5. **Payouts Resource** — List, filters, grouping, detail view
6. **Inventory Resources** — Items, locations, stock, movements
7. **Pallets & Receiving** — List and detail workflows
8. **Vendors & Settings** — Configuration pages
9. **Admin Experience** — Admin-only features
10. **Streamer Experience** — Scoped streamer view
11. **Modals & Actions** — Create, edit, delete workflows
12. **Responsive Design** — Tablet and small mobile testing
13. **Toast Notifications** — Success, error, warning messages
14. **Dark Mode** — Light/dark theme consistency

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
| ![Dashboard overview](tests/Browser/screenshots/desktop/50-dashboard-overview.png) | ![Dashboard overview](tests/Browser/screenshots/mobile/50-dashboard-overview.png) |

The dashboard adapts to the signed-in role:

- **First-run setup checklist** — walks a fresh workspace through connecting a Whatnot channel, running the first import, adding streamers, and creating an inventory location; auto-hides once complete or dismissed.
- **Needs Attention** — a single actionable list (pending streamer logs, shows flagged for channel review, deduction requests awaiting approval, low stock, failed jobs), each deep-linking to the work.
- **Streamer view** — streamers get a scoped overview plus a **Shows to Review** to-do list that jumps straight into enrichment; they only ever see their own shows.
- **Shows Calendar Widget** — a full-width monthly calendar with colour-coded shows by status, month navigation, and a legend.

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

#### Net Margin & Filter Presets — Shows

The Shows list carries a per-show **Net Margin** column (Whatnot net + tips − approved COGS − payouts, green/red with a margin-% tooltip) and one-tap **filter-preset tabs** — *Needs Review*, *This Week*, *Unreconciled*, plus a badged *Channel Review* tab for shows whose channel attribution may be wrong.

| Desktop | Mobile |
|---|---|
| ![Shows net margin & tabs](tests/Browser/screenshots/desktop/54-shows-net-margin.png) | ![Shows net margin & tabs](tests/Browser/screenshots/mobile/54-shows-net-margin.png) |

---

### Show Pipeline — Status Board

A Kanban view of shows moving through the ops pipeline (Pending Review → Mapping → Pending Approval → Reconciled). Each card shows a **time-in-status** aging badge — grey when fresh, amber at 3+ days, red at 7+ — so shows stuck in the reconcile pipeline stand out at a glance.

| Desktop | Mobile |
|---|---|
| ![Status board aging](tests/Browser/screenshots/desktop/53-status-board-aging.png) | ![Status board aging](tests/Browser/screenshots/mobile/53-status-board-aging.png) |

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

<!-- payroll-automation-screenshots -->
#### Payment Structures, Automation & Backfill

Payroll now uses two default compensation structures — **Streamer** and **Fulfillment** — with field-level individual overrides. Streamer show earnings roll into the existing weekly Pay Run, while fulfillment can use the existing hourly/PWE/label/custom-formula inputs. Draft Pay Runs can be created and recalculated automatically, and the historical backfill page supports a read-only dry run before any missing/Draft records are created or recalculated.

| Payment Structures | Individual Override |
|---|---|
| ![Payment Structures](tests/Browser/screenshots/payroll/payment-structures.png) | ![Individual compensation override](tests/Browser/screenshots/payroll/payment-structures-individual-override.png) |

| Backfill Setup | Backfill Preview |
|---|---|
| ![Pay Run Backfill](tests/Browser/screenshots/payroll/pay-run-backfill-empty.png) | ![Historical Pay Run comparison](tests/Browser/screenshots/payroll/pay-run-backfill-preview.png) |

| Weekly Pay Runs | Weekly Pay Run Detail |
|---|---|
| ![Automated weekly Pay Runs](tests/Browser/screenshots/payroll/weekly-pay-runs.png) | ![Weekly earnings breakdown](tests/Browser/screenshots/payroll/weekly-pay-run-detail.png) |

The implementation specification and safety rules are documented in [`docs/PAYROLL_AUTOMATION_SPEC.md`](docs/PAYROLL_AUTOMATION_SPEC.md).

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

### Product Insights

Inventory profitability derived from data already captured — sold order lines, on-hand stock, and costs. Per product: units sold, revenue, COGS and **margin (%)**, **sell-through** (sold ÷ sold + on-hand), capital tied up, and days since last sale. Filter chips switch between **Best Margin**, **All Products**, **Dead Stock** (on-hand with no sale in 90 days), and **Never Sold**; KPI cards summarise total inventory value, dead-stock value, and SKU counts.

| Desktop | Mobile |
|---|---|
| ![Product Insights — best margin](tests/Browser/screenshots/desktop/51-product-insights.png) | ![Product Insights — best margin](tests/Browser/screenshots/mobile/51-product-insights.png) |
| ![Product Insights — dead stock](tests/Browser/screenshots/desktop/52-product-insights-dead-stock.png) | ![Product Insights — dead stock](tests/Browser/screenshots/mobile/52-product-insights-dead-stock.png) |

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

## Interactive UI Components & Actions

All major workflows are supported by interactive modals, buttons, and real-time feedback. Below are the key components tested in the screenshot tour:

### Modals & Dialogs

| Component | Where | What it does |
|-----------|-------|-------------|
| **Create Modal** | Every resource list | Opens form to create new record |
| **Edit Modal** | Every resource detail | Opens form to edit existing record |
| **Delete Confirmation** | Action menu | Confirms destructive action |
| **Item Selection Modal** | Deduction requests | Multi-select inventory items |
| **Stock Adjustment Modal** | Inventory detail | Quick adjust quantity |
| **Payout Approval Modal** | Payout list | Review + approve payout |
| **Manifest Upload Modal** | Pallet create | Upload packing slip for AI parsing |

### Buttons & Actions

| Action Type | Behavior |
|------------|----------|
| **Create** | Opens create modal, saves on submit |
| **Edit** | Opens edit modal with current data |
| **Delete** | Shows confirmation before deleting |
| **Bulk Actions** | Select rows, apply action to multiple |
| **State Transitions** | Changes record status with validation |
| **Export/Download** | Downloads data in requested format |
| **Filters & Search** | Real-time table filtering |

### Notifications

| Type | Style | Duration |
|------|-------|----------|
| **Success** | Green ✅ | Auto-dismiss 3s |
| **Error** | Red ❌ | Manual close |
| **Warning** | Amber ⚠️ | Auto-dismiss 5s |
| **Info** | Blue ℹ️ | Auto-dismiss 4s |

All notifications use `window.showToast()` and appear in fixed top-right corner.

### Dark Mode

- ✅ Toggle in navbar (saves to session)
- ✅ Respects system preference on first visit
- ✅ All components styled for both themes
- ✅ High contrast for accessibility

### Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `?` | Show shortcuts (console) |
| `Ctrl+K` | Global search |
| `Escape` | Close modal |
| `Ctrl+Enter` | Submit form |

---

## Testing Checklist

Quick checklist to verify all functionality works:

### Authentication
- [ ] Login/logout works
- [ ] Invalid credentials rejected
- [ ] Sessions persist

### Resources (Shows, Payouts, Inventory, etc.)
- [ ] List pages load
- [ ] Create/edit/delete modals work
- [ ] Search and filters work
- [ ] Forms validate correctly
- [ ] Success/error messages appear

### Mobile & Responsive
- [ ] Desktop view (1440×900)
- [ ] Mobile view (390×844)
- [ ] Tablet view (768×1024)
- [ ] No horizontal scroll
- [ ] Touch-friendly buttons

### User Roles
- [ ] Super Admin sees everything
- [ ] Admin restricted from role management
- [ ] Streamer only sees own data
- [ ] Unauthorized pages redirect

### Accessibility
- [ ] Keyboard navigation works
- [ ] Dark mode readable
- [ ] Focus indicators visible
- [ ] ARIA labels present

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

To run the queue worker (required for pallet-manifest AI mapping, notifications, and Whatnot sync jobs):

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

**Full setup guide (local dev → production VPS → CI/CD → SSL → AI): [SETUP.md](SETUP.md)**

### VPS quick-start summary

```
① SSH into a fresh Ubuntu 22.04/24.04 VPS
② Clone repo and run: sudo bash deploy/vps-first-time.sh
   → installs Docker, creates /opt/vortexops, starts app + database
③ Run: sudo bash deploy/vps-setup.sh
   → installs Nginx, gets Let's Encrypt SSL cert
④ Add 4 GitHub Secrets (GHCR_PAT, VPS_HOST, VPS_USER, VPS_SSH_KEY)
⑤ Push to main — CI/CD takes it from there on every push
```

Production files:
- Docker image: `Dockerfile` + `docker-compose.yml`
- Env template: `.env.docker.example`
- VPS setup scripts: `deploy/vps-first-time.sh`, `deploy/vps-setup.sh`
- Nginx config: `deploy/nginx.conf`
- SSL guide: `deploy/nginx-ssl.md`
- CI/CD: `.github/workflows/deploy.yml`

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
[Map Items Manually / streamer maps items sold] ──► DeductionRequest + lines created
    │
    ▼
mapping ──► Ops/streamer assigns each line to an inventory item, location, and cost
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
| `pending_review` | Ready for ops to assign streamers and map items |
| `mapping` | Deduction lines are being manually assigned to inventory |
| `pending_approval` | Deduction request created; waiting for ops to approve or reject |
| `reconciled` | Approved and inventory deducted; payouts generated |
| `closed` | Manually closed without reconciliation |
| `cancelled` | Cancelled; no deductions |

---

## Deduction Requests

Each show generates one `DeductionRequest` (one per streamer, raised manually or via items the streamer adds to their log). The request contains one or more `DeductionRequestLine` records, each representing one inventory item to be deducted.

**Approval UI** (`/admin/deduction-requests/{id}`):
- Shows the full show summary (revenue, units sold, streamer)
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

## AI Engine (Ollama)

All AI runs locally via Ollama — no data leaves the server. Three distinct AI systems share the same Ollama instance and `OllamaClient` HTTP abstraction.

```
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
```

Or configure via **Settings → AI Assistant**. Use the "Test Ollama" button to verify connectivity.

```bash
ollama serve
ollama pull llama3.2          # chat + LLM mapping
ollama pull nomic-embed-text  # embeddings (Stage 3 of mapping pipeline)
```

---

### 1. AI Chatbot

The chatbot is a floating sparkles panel (`AiChatPanel` Livewire component) that appears on every admin page. It streams responses token-by-token via Ollama's `/api/chat` endpoint.

**What makes it context-aware:**

- **Skill detection** — `SkillRegistry` inspects the current page URL and injects a domain-specific expert persona into every system prompt. Browse `/admin/shows` and the AI acts as a show operations assistant. Browse `/admin/pallets` and it acts as a receiving assistant. Supported domains: `inventory`, `shows`, `finance`, `operations`, `receiving`, `reporting`, `general`.

- **Live business snapshot** — `ContextBuilder::buildBusinessSummary()` runs a handful of DB queries (show counts by status, active streamers, stock value, pending deductions/payouts, last 5 shows) and injects the results into every system prompt. Cached 60 seconds so rapid messages don't hammer the database.

- **Page-level context** — `ContextBuilder::buildPageContext()` pattern-matches the URL for a specific record ID (e.g. `/admin/shows/42`) and queries that record's live data — show P&L, DR lines, payout breakdown, pallet lines, streamer balance, etc. If you're on a show page and ask "what's the margin?", the numbers are already in context.

- **Conversation history** — the last 10 turns are stored in the PHP session and sent with every request. Conversation persists across page navigation within the same browser session.

**Config:** `num_ctx: 4096`. Non-streaming path (`complete()`) uses `num_predict: 1024` for single-shot queries.

---

### 2. Show Order → Inventory Mapping (Manual)

Matching a sold item on a show to an `InventoryItem` is a manual step, not an AI one. Two paths:

- **Streamer self-service** — on the Streamer Log page's "Items Sold" panel, the streamer maps each imported Whatnot order line to an inventory item (and location/cost) via a dropdown, or adds a line themselves for something that wasn't auto-imported.
- **Admin bulk mapping** — "Map Items Manually" on a show's detail page creates one `DeductionRequestLine` per distinct sold-item description, then the admin assigns each to an inventory item/location on the Deduction Request review page (`ViewDeductionRequest`), which supports adding, editing, and removing lines before approval.

`MappingEngine`'s LLM-assisted stage is still used elsewhere (see the packing-slip parser below and the receiving/pallet manifest import) — it is intentionally not part of the show order reconciliation flow.

---

### 3. Packing Slip / Manifest Image Parser

When creating a pallet, ops can upload a vendor packing slip (PDF, PNG, JPG, WEBP) instead of entering lines manually. The file is parsed by a vision AI model and the extracted lines are shown for review before import.

**Full flow:**

```
Upload file (ImportManifest page)
    │
    ▼
ParsePalletSlipJob dispatched → ai queue, 5 min timeout
    │
    ▼
PalletSlipParser::parse()
    │
    ├── PDF? → convert pages to PNG via Imagick (preferred) or pdftoppm fallback
    │
    └── Image → base64 encode
    │
    ▼
OllamaClient::vision() — sends image + structured prompt to Ollama vision model (moondream)
    │
    ▼
Model returns JSON:
{"lines":[{"description":"...","case_count":1,"unit_cost":89.99,"sku":"ABC123","barcode":"012345678901"}]}
    │
    ▼
PalletSlipParser normalises lines (strips $, coerces types, fills missing fields with null)
    │
    ▼
AiTask marked completed, output stored
    │
    ▼
ImportManifest page polls every 3 s via checkProcessing()
    │
    ▼
On completion: each extracted line runs through MappingEngine::match() (Stages 1–3, LLM skipped)
    │
    ▼
Verify stage — ops sees extracted lines with AI-matched inventory items pre-filled
    │   ├── Edit description, case count, unit cost, SKU, barcode
    │   ├── Override matched item from a dropdown
    │   ├── Toggle "Create new inventory item" for unmatched lines
    │   └── Remove unwanted lines / add manual lines
    │
    ▼
import() — wraps everything in a DB transaction
    ├── Uses AI-matched item ID if present
    ├── Falls back to exact barcode/SKU/name DB lookup
    ├── Creates new InventoryItem if "create new" was checked
    └── Creates PalletLine records for each row
```

**PDF conversion:** Tries the `Imagick` PHP extension first (renders at 150 DPI, flattens alpha). Falls back to `pdftoppm` (poppler-utils) for environments where Imagick isn't available. Throws if neither is present.

**Vision model:** Uses Ollama's `moondream` model by default (or the vision-capable model configured in Settings). The prompt instructs the model to return **only** valid JSON — no markdown, no explanation. The parser has three fallback JSON extraction strategies: direct decode → regex `{"lines":[...]}` extract → bare array extract. Parse failures are logged as warnings and return an empty line set rather than crashing.

**Timeout handling:** If the `ai` queue worker isn't running, the job stays `pending`. After 5 minutes the UI surfaces a timeout message with a prompt to check the worker. Processing timeout (job picked up but stalled) is 10 minutes.

---

### AI infrastructure shared across all three systems

| Class | Role |
|---|---|
| `OllamaClient` | Single HTTP client for all Ollama calls: `generate()`, `chat()`, `chatStream()`, `embed()`, `vision()`, `listModels()`, `isOnline()`. Reads URL and model from Settings at runtime with env fallback. |
| `AiTask` | Polymorphic model tracking every AI job: type, status (`pending → processing → completed / failed`), input, output, error message, triggered by, started/completed timestamps. |
| `MappingResult` | Value object returned by `MappingEngine::match()`: product, stage, confidence, raw score. |

**Queue:** All AI jobs run on the dedicated `ai` queue. Recommended worker:
```bash
php artisan queue:work --queue=ai,default --sleep=3 --tries=1 --timeout=330
```

---

## Whatnot Scraper

Whatnot has no public API. Everything is done through headless browser automation — a Playwright/Node.js script (`scripts/whatnot-scraper.cjs`) that PHP launches as a subprocess via Symfony Process (`app/Services/WhatnotScraper.php`).

```
PHP (WhatnotScraper.php)
    └── Symfony Process
            └── node scripts/whatnot-scraper.cjs
                    └── Playwright → Chromium (headless)
                            └── whatnot.com seller dashboard
```

---

### Authentication — cookie bootstrap

Whatnot uses **Kasada bot protection** on the login page, which blocks headless browsers. VortexOps bypasses this entirely by loading real Chrome session cookies at startup — the scraper navigates straight to Seller Hub without ever touching the login page.

**One-time setup (do this once, repeat when sync starts failing ~30–90 days later):**

1. **Install Cookie-Editor** in Chrome — search for "Cookie-Editor" by cgagnier in the Chrome Web Store
2. **Log into whatnot.com** normally in Chrome, complete any 2FA
3. **Export cookies** — click Cookie-Editor → Export → Export as JSON → save the file
4. **Import into VortexOps:**
   ```bash
   php artisan whatnot:login --cookie-file=~/Downloads/whatnot-cookies.json
   ```
   This validates and saves cookies to `storage/whatnot-cookies.json`.

**Verify / refresh:**
```bash
php artisan whatnot:login --test         # test whether existing cookies are still valid
php artisan whatnot:login --cookie-file= # re-import fresh cookies when expired
```

If `WHATNOT_EMAIL` and `WHATNOT_PASSWORD` are set in `.env`, the command tries a headless form login first as a convenience — but this usually fails on accounts with Kasada challenges. The cookie import path always works.

Cookies are stored at `storage/whatnot-cookies.json`. The scraper loads them into its Playwright context at startup, skipping the login page entirely on every run.

---

### 6 operating modes (`WHATNOT_MODE`)

| Mode | What it does |
|---|---|
| `analytics` | Scrapes per-show metrics from `/dashboard/analytics/overview` — the default for all imports |
| `show-orders` | Scrapes the order/lot list from one specific show detail page (`WHATNOT_SHOW_URL` required) |
| `seller-shows` | Scrapes `/seller/shows` to collect show detail URLs for backfilling |
| `test` | Navigates to Seller Hub and returns `{connected, email, seller_url}` |
| `cookie-test` | Checks whether current cookies give access to Seller Hub without a login page redirect |
| `discover` | Crawls all Seller Hub nav pages and intercepts every JSON API call to build an endpoint map |

---

### Mode: analytics (the main import)

This is what runs when you hit "Import from Whatnot" in the admin UI.

**What it navigates to:** `whatnot.com/dashboard/analytics/overview` → clicks the "Shows" tab.

**How it paginates:** The analytics page shows one show at a time. There are two arrow buttons: "See older show" and "See newer show". The scraper starts at the newest show and clicks "See older show" repeatedly, up to `WHATNOT_LIMIT` times (default 50), stopping when the button is disabled (no more history).

**How it extracts data per show:** `extractAnalyticsMetrics()` runs entirely inside the browser via `page.evaluate()`. It finds metric cards by their inline CSS (`height: 160px`, `border-radius: 16px`), then reads the name (16px bold) and value (32px bold) from each card. The show title and date are found by their own inline style signatures. No class names or test IDs are used — those change with every Whatnot deploy.

**Fields captured per show:**

| Field | Whatnot metric label |
|---|---|
| `gross_revenue` | Estimated Sales |
| `whatnot_net` | Total Estimated Earnings |
| `completed_earnings` | Completed Earnings |
| `units_sold` | Orders |
| `avg_order_value` | Average Order Value |
| `giveaway_spend` | Giveaway Spend |
| `giveaways_count` | Giveaways |
| `buyers_count` | Buyers |
| `first_time_buyers` | First Time Buyers |
| `returning_buyers` | Returning Buyers |
| `shares_count` | Shares |
| `show_duration` | Show Duration (converted to minutes) |
| `max_concurrent_viewers` | Max Concurrent Viewers |
| `total_views` | Total Views |
| `avg_order_rating` | Average Order Rating |
| `detail_url` | Extracted from anchor `href` matching `/live/<username>/<id>` |

**How PHP processes the results (`WhatnotScraper::importShows`):**

1. For each show in the raw array, matches an existing record by `title + show_date` (or just one if the other is missing)
2. **Existing show** → updates the analytics fields only (revenue, viewers, duration, etc.); never changes status, streamers, or ops data
3. **New show** → creates with `status = draft`, `import_source = auto_whatnot`, then immediately calls `detectStreamers()`

**Streamer auto-detection (`Show::detectStreamers`):**
Runs on every newly imported show. Scans all active streamer names against the show title:
- Full name found in title → `high` confidence
- Any 4+ character name part found → `medium` confidence

Suggestions are stored in `ai_streamer_suggestion` (JSON column). If the show has no streamers attached yet, high-confidence matches are auto-attached immediately. Medium matches are stored for ops to confirm manually.

---

### Mode: show-orders

Scrapes the individual lot/order list from a show's detail page (requires `detail_url` to be set on the show first).

**What it navigates to:** the show's own page on whatnot.com, then clicks the "Lots", "Orders", or "Sales" tab (tries all three names since Whatnot has used different labels).

**How it extracts orders:** Whatnot uses dynamic/obfuscated CSS class names on show pages, so the scraper uses two structural strategies:

1. **Strategy A — lot number walk:** Uses a `TreeWalker` to find all text nodes matching `"Lot #N"`, walks up the DOM to the nearest container element (TR / LI / ARTICLE, or a flex div with a fixed height), then extracts price (`$N.NN` patterns), buyer (`@username` pattern), status (`sold`/`completed`/`refunded`/etc.), and item name (longest non-metadata line in the container).

2. **Strategy B — table rows:** If Strategy A finds nothing, scans all `<tr>` and `[role="row"]` elements for dollar amounts. Used as a fallback when Whatnot has changed to a table layout.

If both strategies return nothing, the scraper exits with code 2 (selector miss) and dumps a page snapshot to stderr for debugging.

**Fields captured per order:** `lot_number`, `buyer_username`, `item_name`, `quantity`, `unit_price`, `total_price`, `status`, `raw_data` (full text of the container, max 400 chars).

**Deduplication (`importShowOrders`):** Pre-loads all existing `WhatnotShowOrder` records for the show in two queries, then deduplicates by `whatnot_order_id` when present, or falls back to the composite key `buyer|item_name|lot_number`.

---

### Mode: seller-shows (URL backfill)

Scrapes `/seller/shows` to collect detail URLs for past shows and backfill them on existing `Show` records. Matches by title + date; falls back to date-only or title-only if both aren't available.

Used when shows were imported before `detail_url` was being captured, or to repair missing links.

---

### Multi-channel support

Each `WhatnotChannel` record has `whatnot_username` and `include_in_import`. `importAllEnabledChannels()` loops all active channels with `include_in_import = true` and runs a full analytics import per channel.

**Channel switching:** Before scraping, if a `WHATNOT_CHANNEL_NAME` is set, the scraper:
1. Navigates to the Whatnot home page
2. Opens the navigation sidebar (tries profile avatar, aria-labelled buttons, drawer triggers in order)
3. Clicks "Switch Role"
4. Clicks the target channel by name from the role list

If the sidebar won't open, the scraper logs a warning and continues on whatever channel is currently active rather than failing.

---

### Selector fragility & debugging

Whatnot deploys UI changes without notice. When a selector breaks:

- **Exit code 0** — success, JSON on stdout
- **Exit code 1** — login/nav error, message on stderr
- **Exit code 2** — selector miss; page structure changed

When you get exit code 2:
```bash
WHATNOT_DEBUG=1 php artisan whatnot:import
# Saves /tmp/whatnot-debug-*.png screenshots at each step
# Check the logs for PAGE_SNAPSHOT with the raw HTML
# Update the SELECTORS object at the top of scripts/whatnot-scraper.cjs
```

The `SELECTORS` constant at the top of the script is the single place to update when Whatnot changes their markup. Analytics selectors use `aria-controls`/`aria-label` attributes and inline style patterns rather than class names, which are more stable across deploys.

---

### Discover mode — mapping Whatnot's API

Because Whatnot has no public API, the discover mode crawls Seller Hub and intercepts every JSON response the page makes, building a structured map of endpoints, payloads, and data shapes. Run it once after a fresh cookie import:

```bash
# One-time: import cookies
php artisan whatnot:login --cookie-file=~/Downloads/whatnot-cookies.json

# Map all Seller Hub API endpoints
php artisan whatnot:import --discover
```

The scraper navigates each section of the Seller Hub (dashboard, analytics, shows, orders, payouts, settings), listens on `page.on('response', ...)` for JSON responses from `*.whatnot.com`, and outputs a JSON endpoint map to stdout. The command saves it and pretty-prints a summary of all captured endpoints.

You can re-run discover any time Whatnot updates their Seller Hub to check whether endpoint paths or payload shapes have changed.

> **Interactive browser alternative?** Embedding a real browser inside Filament would require server-side VNC (noVNC), which adds significant infrastructure complexity. Discover mode achieves the same result automatically — it clicks through every Seller Hub page for you and captures every API call. There is no advantage to a manual click-through approach; discover mode is the right tool.

---

### Chromium resolution

The script finds Chromium via a priority chain so it works across dev, Docker, and bare VPS:

1. `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` env var (explicit override)
2. Marker files written by `php artisan whatnot:setup-chromium` (VPS) or Docker build
3. Playwright's own API (`chromium.executablePath()`)
4. Directory scan of `PLAYWRIGHT_BROWSERS_PATH` and `~/.cache/ms-playwright`
5. System Chromium (`/usr/bin/chromium`, `/usr/bin/chromium-browser`, `/usr/bin/google-chrome`)

**Setup commands:**
```bash
# VPS: installs Chromium and writes the marker file
php artisan whatnot:setup-chromium

# Docker: Playwright browsers are pre-installed at /opt/pw-browsers
# The PLAYWRIGHT_BROWSERS_PATH env var is set in docker-compose.yml
```

---

## Whatnot Sync Engine

The Sync Engine (`app/Services/WhatnotSyncEngine.php`) ties the scraper to the database — it syncs shows, orders, and buyer profiles from Whatnot into VortexOps on a schedule. Each sync run is logged in the `whatnot_syncs` table for full audit history.

```
Scheduler (hourly)
    └── whatnot:sync
            └── WhatnotSyncEngine::syncAll()
                    └── per WhatnotChannel:
                            ├── importShows()        → Shows table
                            ├── syncOrdersForChannel() → whatnot_show_orders
                            └── syncBuyersForChannel() → whatnot_buyers
```

---

### Sync types

| Type | Shows targeted | Use case |
|---|---|---|
| `incremental` | Shows with no orders, or not synced in 7+ days | Default hourly background run |
| `last_30_days` | Shows from the past 30 days | Catch up on recent shows |
| `full` | All shows on record | Full resync after a long outage |

---

### Manual sync commands

```bash
# Incremental sync — all active channels
php artisan whatnot:sync

# Sync a specific channel
php artisan whatnot:sync --channel=1

# Last-30-days resync
php artisan whatnot:sync --type=last_30_days

# Dispatch as a background queue job instead of running inline
php artisan whatnot:sync --queue
```

The command prints a results table showing shows/orders/buyers created and updated per channel.

---

### Sync Dashboard (Filament)

**Admin → Streams → Whatnot Sync** — shows each active channel with its last sync status, plus recent sync history (up to 20 runs). From here you can trigger any sync type per-channel or across all channels.

Sync runs display created/updated counts for shows, orders, and buyers, plus an error count and a link to the error detail when something goes wrong.

---

### Buyer profiles

Every sync run aggregates order data per buyer into `whatnot_buyers`:

| Column | Description |
|---|---|
| `username` | Whatnot handle (unique key) |
| `display_name` | Display name if available |
| `total_orders` | All-time order count across your channels |
| `lifetime_spend` | Sum of all order totals |
| `avg_order_value` | `lifetime_spend / total_orders` |
| `first_purchase_date` | Earliest order date |
| `last_purchase_date` | Most recent order date |

**Admin → Streams → Buyers** lists all profiles sorted by lifetime spend. Filters: Repeat Buyers (2+ orders), High Value ($100+ lifetime).

---

### Scheduled sync

The hourly sync is registered in `routes/console.php`:

```php
Schedule::command('whatnot:sync')
    ->hourly()
    ->name('whatnot-sync-hourly')
    ->withoutOverlapping(10);
```

`withoutOverlapping(10)` prevents a second sync from starting if the previous one is still running (with a 10-minute lock TTL as a safety release). The sync runs in the main process when invoked by the scheduler; use `--queue` to offload long runs to the queue worker.

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
     ├──< WhatnotSync (type, status, counters, errors, started_at)
     │
     └──< Show >────────────────────────────< show_streamer >─────────< Streamer
              │                                                               │
              ├──< WhatnotShowOrder >──────────── whatnot_buyer_id ──< WhatnotBuyer
              │                                                         (username, lifetime_spend,
              ├──< DeductionRequest >─────< DeductionRequestLine         total_orders, ...)
              │         │                       │          │
              │   approved_by (User)     InventoryItem  Location   InventoryLocation (streamer_id FK)
              │                                                               │
              └──< Payout >──< WeeklyPayoutBatch                       InventoryStock
                    │                                                        │
                 Streamer                                              InventoryItem

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
│   │   ├── LogViewer.php              # log file browser with level filter + search
│   │   └── WhatnotSyncPage.php        # sync dashboard: trigger syncs, view sync history
│   ├── Resources/
│   │   ├── ShowResource.php            # show CRUD + status-driven next-step action + QueryBuilder filters
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
│   │   ├── WhatnotBuyerResource.php   # buyer profiles — lifetime spend, order counts, dates
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
│   ├── SendLowStockNotification.php    # queued, dispatched after commit
│   └── RunWhatnotSyncJob.php          # queued sync job (timeout=600s)
├── Livewire/
│   ├── AiChatPanel.php                 # floating AI chat sidebar
│   └── FeedbackWidget.php             # screenshot capture + annotation + submit
├── Models/
│   ├── Show.php · DeductionRequest.php · DeductionRequestLine.php
│   ├── InventoryItem.php · InventoryLocation.php · InventoryMovement.php · InventoryStock.php
│   ├── Streamer.php · WhatnotChannel.php
│   ├── Payout.php · WeeklyPayoutBatch.php
│   ├── WhatnotBuyer.php               # buyer profiles + aggregate recalculation
│   ├── WhatnotSync.php                # sync run log (type, status, counters, errors)
│   ├── WhatnotShowOrder.php           # individual orders with buyer FK + shipping fields
│   ├── FeedbackTicket.php
│   ├── User.php · Setting.php · AiLog.php
└── Services/
    ├── InventoryService.php             # all stock mutations, transactions
    ├── OllamaService.php               # Ollama HTTP client + AI log
    ├── PayoutService.php               # payout calculation + weekly batch creation
    ├── WhatnotSyncEngine.php           # shows → orders → buyers sync pipeline
    ├── WhatnotScraper.php              # Playwright subprocess wrapper + cookie auth helpers
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
| **Phase 5** | Automation & Expansion — Whatnot sync engine (shows/orders/buyers), cookie-based auth, discover mode endpoint mapping, buyer profiles, scheduled hourly sync | ✅ Complete |
| **Phase 6** | Advanced Analytics & Webhooks — real-time Whatnot API integration (when available), automated alerts, per-show profitability dashboards | Planned |

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

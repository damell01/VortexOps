# VortexOps

Internal operations platform for **Vortex Breaks** — a Whatnot-based sports card break business.

Built with **Laravel 13** + **Filament v5**. Phase 1: Inventory Foundation.

---

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 |
| Admin panel | Filament v5 |
| Database | SQLite (dev) / MySQL (prod) |
| Auth & Roles | Spatie Laravel Permission v7 |
| Audit log | Spatie Activitylog v5 |
| Queue | Laravel Queues (database driver) |
| AI | Ollama (local LLM, no external API) |

---

## Key design constraints

- **Single-tenant only** — not a SaaS platform
- **Inventory deductions never happen automatically** — every deduction requires explicit approval
- **Full audit trail** — every inventory change creates an immutable movement record
- **Whatnot channels are shared** — multiple streamers can work on the same channel

---

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoDataSeeder   # optional rich demo data
php artisan serve
```

Admin login: `admin@vortexbreaks.com` / `password`

---

## Screenshots

### Login

![Login page](docs/screenshots/00-login.png)

---

### Dashboard

The dashboard shows real-time stats across the entire inventory, low-stock alerts, recent movement activity, a per-location breakdown, and active streamers.

![Dashboard](docs/screenshots/01-dashboard.png)

---

### Inventory Items

#### Items list

All inventory items with category badges, total quantity across all locations, reorder-level warnings, and per-row action menus.

![Items list](docs/screenshots/02-inventory-items-list.png)

#### Filters panel

Filter by category, low-stock threshold, and active/inactive status.

![Items filters](docs/screenshots/03-inventory-items-filters.png)

#### Create item

Form for creating a new inventory item with SKU, name, category, unit cost, and reorder level.

![Create item](docs/screenshots/04-inventory-items-create.png)

#### Item detail

View a single item. The view page provides quick access to all stock operations via the header action button.

![Item detail](docs/screenshots/05-inventory-item-view.png)

---

### Inventory Actions

Each item row has a grouped action menu with five stock operations. All operations are wrapped in database transactions and create mandatory movement log entries.

#### Action menu

![Action dropdown](docs/screenshots/06-inventory-actions-dropdown.png)

#### Add Stock

Add units to any location. Logs a movement record with the selected movement type (opening balance, adjustment, return, etc.).

![Add Stock modal](docs/screenshots/07-add-stock-modal.png)

#### Transfer Stock

Move units between two locations. Creates paired debit/credit movement records.

![Transfer Stock modal](docs/screenshots/08-transfer-stock-modal.png)

#### Adjust Inventory

Set the exact quantity for a location. Computes the delta and logs a positive or negative adjustment movement.

![Adjust Inventory modal](docs/screenshots/09-adjust-inventory-modal.png)

---

### Inventory Locations

#### Locations list

All storage locations with type badges, streamer assignments, and aggregate SKU count.

![Locations list](docs/screenshots/10-inventory-locations-list.png)

#### Create location

Location type drives conditional field visibility — selecting **Streamer Inventory** reveals the streamer assignment field.

![Create location form](docs/screenshots/11-inventory-locations-create.png)

#### Streamer inventory type — conditional field

When the location type is `streamer_inventory`, the streamer selector appears automatically.

![Conditional streamer field](docs/screenshots/12-location-streamer-field.png)

#### Location detail

![Location detail](docs/screenshots/13-inventory-location-view.png)

---

### Stock Levels

Read-only view of every item × location combination showing current quantity, unit cost, and computed stock value. Records cannot be created or deleted here — all changes go through the inventory actions.

![Stock levels](docs/screenshots/14-stock-levels.png)

#### Stock levels filters

Filter by location, item, or quantity range.

![Stock levels filters](docs/screenshots/15-stock-levels-filters.png)

---

### Movement Log

Immutable audit trail. Every stock operation — add, transfer, adjustment, sale deduction, return, damaged — creates a permanent record. Records cannot be created, edited, or deleted through the UI.

![Movement log](docs/screenshots/16-movement-log.png)

#### Movement log filters

Filter movements by type, item, or location.

![Movement log filters](docs/screenshots/17-movement-log-filters.png)

---

### Streamers

#### Streamers list

All streamers with payout type badges, status (active / inactive / on leave), and tips configuration at a glance.

![Streamers list](docs/screenshots/18-streamers-list.png)

#### Create streamer

The payout section uses conditional fields — only the relevant rate fields appear based on the selected payout type.

![Create streamer form](docs/screenshots/19-streamers-create.png)

#### Payout type — Profit Share

Profit share shows the payout percentage field.

![Profit share payout](docs/screenshots/20-streamer-profit-share.png)

#### Payout type — Package Rate

Package rate shows the per-slot package rate field.

![Package rate payout](docs/screenshots/21-streamer-package-rate.png)

#### Payout type — Hourly

Hourly shows the hourly rate field.

![Hourly rate payout](docs/screenshots/22-streamer-hourly-rate.png)

#### Streamer detail

![Streamer detail](docs/screenshots/23-streamer-view.png)

---

### Whatnot Channels

#### Channels list

All company Whatnot channels. Channels are shared — multiple streamers can operate on the same channel.

![Channels list](docs/screenshots/24-whatnot-channels-list.png)

#### Create channel

![Create channel form](docs/screenshots/25-whatnot-channels-create.png)

---

### User Management

Full user CRUD with role assignment. Accessible to admins only.

![Users list](docs/screenshots/36-users-list.png)

![Create user](docs/screenshots/37-users-create.png)

**Roles:**
| Role | Access |
|---|---|
| `admin` | Full access to all resources, settings, and user management |
| `streamer` | Inventory items, their own locations + shared locations, movement log. No settings, no user management. |

When a user is assigned the `streamer` role, link them to a Streamer profile via the **Linked Streamer Profile** field. Inventory locations are then automatically scoped to their profile + all shared (non-streamer) locations.

---

### Activity Log

Every model change — creates, updates, deletes — is automatically captured by Spatie Activitylog. The log is immutable and admin-only.

![Activity log](docs/screenshots/38-activity-log.png)

#### Diff view

Click any log entry to see a before/after comparison table showing exactly which fields changed.

![Activity log detail](docs/screenshots/39-activity-log-detail.png)

---

### Settings

Settings page controls branding and AI configuration. Admin-only.

![Settings page](docs/screenshots/31-settings-page.png)

**Branding options:**
- **Logo** — upload PNG/JPG/SVG (max 2 MB). Displays in the sidebar header.
- **Brand name** — shown as text when no logo is set.
- **Primary color** — 8 preset swatches or a custom hex/color-picker. Changes the color of buttons, badges, active nav items, and all accent elements across the panel.

![Color changed to blue](docs/screenshots/35-settings-color-changed.png)

All changes apply on the next page load (color and logo are read from settings on every request, cached for 1 hour).

---

### Notifications

Database notifications appear in the bell icon in the Filament header (polled every 30 seconds). Two types are dispatched automatically:

- **Low Stock** (warning) — fired after any stock operation (add, transfer, adjust, return) when the item's total quantity falls at or below its reorder level.
- **Damaged Items** (danger) — fired immediately when units are moved to the damaged location via Mark Damaged.

All notifications are sent to every user in the system and stored in the `notifications` table.

![Dashboard with notification bell](docs/screenshots/01-dashboard.png)

---

### Settings

The Settings page controls the global AI toggle and Ollama connection. All changes persist to the database immediately.

![Settings page](docs/screenshots/31-settings-page.png)

**AI toggle off:**

![AI disabled](docs/screenshots/34-settings-ai-disabled.png)

When AI is disabled, the floating button is removed from every page and no AI requests can be made.

---

### AI Assistant (Ollama)

The AI connects to a local [Ollama](https://ollama.com) instance. No data leaves your server.

#### Floating panel (available on every page)

A persistent sparkles button sits in the bottom-right corner of every admin page. Click it to open a chat panel that automatically loads context for whatever you're currently viewing.

**Dashboard — full inventory overview loaded as context:**

![AI panel on dashboard](docs/screenshots/29-ai-panel-dashboard.png)

**Inventory item detail — item stock levels and recent movements loaded:**

![AI panel on item](docs/screenshots/30-ai-panel-item-detail.png)

**Location page — all stock at that location loaded:**

![AI panel on location](docs/screenshots/32-ai-panel-location-context.png)

**Streamer page — streamer's locations and payout config loaded:**

![AI panel on streamer](docs/screenshots/33-ai-panel-streamer-context.png)

The context badge in the panel header always shows what the AI currently knows about. Hit the refresh button (↺) to reload context after navigating.

#### Dedicated AI Assistant page

Full-screen chat with three quick-action buttons for common analysis tasks.

![AI Assistant page](docs/screenshots/26-ai-assistant-empty.png)

**Quick actions:**
| Action | Description |
|---|---|
| Inventory Health | Key concerns, urgent items, one recommendation |
| Reorder Suggestions | Low-stock priorities with estimated order quantities |
| Movement Analysis | Anomalies and high-velocity items from recent movements |

#### AI Logs

Read-only table of every AI interaction — model used, action type, latency, success/fail status. Click any row to see the full prompt, response, and inventory snapshot that was sent.

![AI Logs](docs/screenshots/28-ai-logs.png)

**Setup:**

```bash
ollama serve
ollama pull llama3.2   # or any model you prefer
```

Configure in `.env` or via the Settings page:

```
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
OLLAMA_TIMEOUT=60
```

The panel degrades gracefully if Ollama is offline — a red dot shows in the button and the input is disabled until Ollama comes back online.

---

## Data model

```
Streamer ──< InventoryLocation >──< InventoryStock >── InventoryItem
                    │                                       │
                    └──────────────────────────────────────┘
                                InventoryMovement
                         (from_location, to_location, qty, type)

WhatnotChannel  (standalone — shared by multiple streamers)
```

### Movement types

| Type | When created |
|---|---|
| `opening` | Initial stock entry |
| `transfer` | Stock moved between locations |
| `adjustment` | Quantity corrected |
| `sale_deduction` | Manual sale deduction (requires approval) |
| `return` | Item returned to inventory |
| `damaged` | Item marked as damaged |

---

## Project structure

```
app/
├── Filament/
│   ├── Resources/
│   │   ├── InventoryItemResource.php       # 5 stock action modals
│   │   ├── InventoryLocationResource.php
│   │   ├── InventoryMovementResource.php   # read-only audit log
│   │   ├── InventoryStockResource.php      # read-only stock view
│   │   ├── StreamerResource.php
│   │   └── WhatnotChannelResource.php
│   └── Widgets/
│       ├── StatsOverviewWidget.php
│       ├── LowStockWidget.php
│       ├── RecentMovementsWidget.php
│       ├── InventoryByLocationWidget.php
│       └── ActiveStreamersWidget.php
├── Models/
│   ├── InventoryItem.php
│   ├── InventoryLocation.php
│   ├── InventoryMovement.php
│   ├── InventoryStock.php
│   ├── Streamer.php
│   └── WhatnotChannel.php
└── Services/
    └── InventoryService.php    # all stock operations, DB transactions
```

---

## Roadmap

- **Phase 2** — Break management (break types, slots, buyers)
- **Phase 3** — Payout calculation engine
- **Phase 4** — Whatnot integration (order sync)
- **Phase 5** — Reporting & analytics

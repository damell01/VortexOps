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

### Notifications

Database notifications appear in the bell icon in the Filament header (polled every 30 seconds). Two types are dispatched automatically:

- **Low Stock** (warning) — fired after any stock operation (add, transfer, adjust, return) when the item's total quantity falls at or below its reorder level.
- **Damaged Items** (danger) — fired immediately when units are moved to the damaged location via Mark Damaged.

All notifications are sent to every user in the system and stored in the `notifications` table.

![Dashboard with notification bell](docs/screenshots/01-dashboard.png)

---

### AI Assistant (Ollama)

The AI Assistant connects to a local [Ollama](https://ollama.com) instance and provides inventory intelligence without sending data to any external service.

#### Assistant page

Chat interface with three pre-built quick actions and a free-form question input.

![AI Assistant](docs/screenshots/26-ai-assistant-empty.png)

![AI Assistant with question](docs/screenshots/27-ai-assistant-typed.png)

**Quick actions:**
| Action | Description |
|---|---|
| Inventory Health | Summarises key concerns, urgent items, and one recommendation |
| Reorder Suggestions | Prioritises low-stock items with estimated reorder quantities |
| Movement Analysis | Identifies anomalies and high-velocity items from recent movements |

Every AI interaction is logged with the full prompt, response, context snapshot, latency, and the user who triggered it.

#### AI Logs

Read-only audit trail of every AI interaction. View the full prompt and response for any log entry.

![AI Logs](docs/screenshots/28-ai-logs-list.png)

**Setup:**

```bash
# Install and start Ollama
ollama serve
ollama pull llama3.2

# Configure in .env (defaults shown)
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
OLLAMA_TIMEOUT=60
```

The assistant degrades gracefully if Ollama is offline — the status bar shows a red indicator and the send button remains functional (requests will error and log the failure).

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

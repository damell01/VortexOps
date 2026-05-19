# VortexOps — Technical Reference

This document is the "how does this actually work" companion to the README. Use it when making changes, debugging unexpected behavior, or adding features to an existing system.

---

## Table of contents

1. [Database & migrations](#database--migrations)
2. [Settings system](#settings-system)
3. [Show workflow — state machine](#show-workflow--state-machine)
4. [Deduction request lifecycle](#deduction-request-lifecycle)
5. [Inventory mutations — how stock changes](#inventory-mutations--how-stock-changes)
6. [Payout calculation engine](#payout-calculation-engine)
7. [AI pipeline (Ollama)](#ai-pipeline-ollama)
8. [Queue jobs](#queue-jobs)
9. [Filament resources — patterns used](#filament-resources--patterns-used)
10. [Roles & access control](#roles--access-control)
11. [Notifications](#notifications)
12. [Activity log](#activity-log)
13. [Branding & theming](#branding--theming)
14. [Adding a new feature — checklist](#adding-a-new-feature--checklist)

---

## Database & migrations

**Driver:** SQLite in development (`database/database.sqlite`), MySQL in production. No driver-specific SQL is used — all queries go through Eloquent or the query builder.

**Migration naming:** All migrations use timestamps as prefixes. Phase 1 inventory migrations are `2026_05_17_*`, settings/AI are `2026_05_18_*`, Phase 2–3 (shows, deductions, payouts) are `2026_05_19_5*`. The `500*` suffix for Phase 2–3 ensures they always run after Phase 1 regardless of the timestamp sort.

**Reset & reseed:**

```bash
php artisan migrate:fresh --seed
```

This runs all three seeders in order (defined in `DatabaseSeeder.php`):
1. `DefaultDataSeeder` — roles, default inventory locations, default Whatnot channel
2. `SuperAdminSeeder` — creates `dev@vortexbreaks.com` / `devpassword` with `super_admin` role
3. `DemoDataSeeder` — streamers, inventory items, stock, shows, deduction requests, payouts, weekly batches

**Key tables:**

| Table | Purpose |
|---|---|
| `shows` | One row per Whatnot show |
| `show_streamer` | Pivot: many-to-many shows ↔ streamers with `is_primary` flag |
| `deduction_requests` | One per show (per streamer at mapping time) |
| `deduction_request_lines` | Line items within a deduction request |
| `inventory_items` | Product catalogue (SKU, cost, reorder level) |
| `inventory_locations` | Physical storage locations |
| `inventory_stock` | Current quantity per item per location |
| `inventory_movements` | Immutable log of every stock change |
| `payouts` | Calculated payout per streamer per show |
| `weekly_payout_batches` | Groups payouts into a weekly pay run |
| `settings` | Key/value app configuration |
| `ai_logs` | Record of every Ollama API call |
| `activity_log` | Spatie model change history |
| `notifications` | Laravel database notifications |

---

## Settings system

**Model:** `App\Models\Setting`

Settings are stored as key/value strings in the `settings` table. All reads are cached (cache key `setting.{key}`, TTL 1 hour by default).

**Read:**
```php
Setting::get('brand_name', 'VortexOps');  // with default
Setting::getBool('ai_enabled', true);      // casts '1'/'true'/'yes' → true
```

**Write:**
```php
Setting::set('brand_name', 'New Name');    // writes + clears cache for that key
```

**To add a new setting:**
1. Add a public property to `AppSettings.php` (the Livewire page)
2. Load it in `AppSettings::mount()`
3. Save it in `AppSettings::saveSettings()` — validate first if needed
4. Add the UI field to `resources/views/filament/pages/app-settings.blade.php`
5. Read it anywhere else via `Setting::get('your_key')`

The settings page is at `/admin/settings`. Access is restricted to admins via `canAccess()`.

---

## Show workflow — state machine

Shows move through a fixed set of statuses. There is no formal state machine class — transitions happen inline in the relevant service or page action.

```
draft
  │
  ▼ (CreateShow::afterCreate)
pending_review
  │
  ▼ (MapShowInventory job dispatched)
mapping
  │
  ▼ (AiInventoryMapperService::map, on success)
pending_approval
  │
  ├── Approve (DeductionApprovalService::approve)
  │     └──► reconciled
  │
  └── Reject (DeductionRejectionService::reject)
        └──► pending_review  (ops can re-run AI mapping or edit manually)

Any status:
  └── Cancel action → cancelled
```

**Where each transition lives:**

| Transition | File |
|---|---|
| `draft → pending_review` | `ShowResource/Pages/CreateShow.php :: afterCreate()` |
| `pending_review → mapping` | `AiInventoryMapperService::map()` — called by `MapShowInventory` job |
| `mapping → pending_approval` | `AiInventoryMapperService::map()` — after DB transaction commits |
| `mapping → pending_review` (failure) | `AiInventoryMapperService::map()` — catch block |
| `pending_approval → reconciled` | `DeductionApprovalService::approve()` |
| `pending_approval → pending_review` | `DeductionRejectionService::reject()` |
| `* → cancelled` | `ShowResource::table()` — "Cancel" table action |

**To add a new status:** add it to `Show::statusLabels()`, add the badge color in `ShowResource::table()` column definition, and add the transition in the appropriate service or page action.

---

## Deduction request lifecycle

A `DeductionRequest` is always created by `AiInventoryMapperService` (never manually from the UI — `canCreate()` returns false). Ops can only view, edit lines, approve, or reject.

**Creation flow:**
1. `MapShowInventory` job calls `AiInventoryMapperService::map($show)`
2. Service loads streamer's inventory locations + all shared locations with their stock
3. Builds a `$stockCatalogue` array (item_id, location_id, name, sku, cost, qty)
4. Sends prompt to Ollama's `json()` endpoint, expecting `{"mapping_notes": "...", "lines": [...]}`
5. Wraps creation in a DB transaction: creates `DeductionRequest` then all `DeductionRequestLine` records
6. Sets `quantity_approved = quantity_suggested` initially (ops can override)

**Approval flow (`DeductionApprovalService::approve`):**
1. `ViewDeductionRequest::persistLines()` is called first — writes all UI edits back to `deduction_request_lines`
2. `approve()` iterates `$request->lines`, skips any with `quantity_approved <= 0`
3. For each line: calls `InventoryService::deductStock()` (wrapped in the outer transaction)
4. Updates `deduction_requests` with `status=processed`, `approved_by`, `approved_at`, `processed_by`, `processed_at`
5. Updates `shows` with `status=reconciled`
6. Dispatches `NotifyShowReconciled` job after commit

**Rejection flow (`DeductionRejectionService::reject`):**
1. Updates `deduction_requests` with `status=rejected`, `rejection_reason`
2. Updates `shows` with `status=pending_review`
3. No inventory changes, no notifications

**To add a new field to deduction lines:**
1. Add a migration (`add_X_to_deduction_request_lines_table`)
2. Add to `DeductionRequestLine::$fillable` and `$casts` if needed
3. Add the field to the `Repeater` schema in `ViewDeductionRequest::form()`
4. Add it to the `$default` closure that loads existing data into the repeater
5. Handle it in `ViewDeductionRequest::persistLines()` for both update and create branches

---

## Inventory mutations — how stock changes

All stock mutations go through `App\Services\InventoryService`. Never update `inventory_stock.quantity` directly.

**Every method:**
- Is wrapped in `DB::transaction()`
- Creates an `InventoryMovement` record (immutable log)
- Calls `notifyIfLowStock()` after the mutation
- Throws `RuntimeException` if stock is insufficient (before any mutation)

**`addStock(item, location, quantity, movementType, reason)`**
- Upserts `inventory_stock` row, increments quantity
- Movement: `to_location_id = location`, `from_location_id = null`

**`transferStock(item, from, to, quantity, reason)`**
- Decrements `from` stock, increments `to` stock
- Movement: both `from_location_id` and `to_location_id` set

**`adjustStock(item, location, newQuantity, reason)`**
- Sets quantity to exact `newQuantity` value
- Computes `diff = newQuantity - current`; if diff == 0, returns early (no movement created)
- Movement type: `adjustment`; uses `from_location_id` or `to_location_id` depending on sign of diff

**`markDamaged(item, from, damagedLocation, quantity, reason)`**
- Decrements `from` stock, increments `damagedLocation` stock
- Movement type: `damaged`
- Also fires a danger database notification to all users immediately

**`moveToReturns(item, from, returnsLocation, quantity, reason)`**
- Decrements `from` stock, increments `returnsLocation` stock
- Movement type: `return`

**`deductStock(item, location, quantity, reason, referenceId)`**
- Decrements `location` stock; no destination (stock leaves the system)
- Movement type: `sale_deduction`; sets `reference_type='deduction_request'` and `reference_id` if provided
- Called by `DeductionApprovalService` for each approved line

**To add a new stock operation:**
1. Add a method to `InventoryService` following the same pattern (transaction + movement + notify)
2. Add the action modal to `InventoryItemResource::table()->actions()` inside the existing `ActionGroup`
3. The action's `->form()` collects the inputs, `->action()` calls the service method

---

## Payout calculation engine

**Entry point:** `PayoutService::calculateForShow(Show $show)`

Called by `DeductionApprovalService::approve()` after reconciliation. Iterates all streamers on the show, calculates each payout, and upserts `Payout` records (creates or updates existing draft).

**Calculation inputs (from the show):**

| Field | Used for |
|---|---|
| `shows.whatnot_net` | The net revenue after Whatnot fees — used as the base for profit share |
| `shows.tips` | Total tips for the show — split equally among all streamers if `include_tips = true` |
| `shows.show_duration` | Duration in minutes — used for hourly calculation |

**Per streamer fields (from `streamers` table):**

| Field | Used for |
|---|---|
| `payout_type` | `profit_share`, `package`, `hourly`, `flat_rate` |
| `payout_percentage` | Profit share only — e.g. `30` = 30% |
| `package_rate` | Package and flat_rate — fixed dollar amount |
| `hourly_rate` | Hourly only |
| `include_tips` | Whether to add tips share to this streamer's payout |

**Multi-streamer shows:** `whatnot_net` is divided equally among all streamers on the show (`netRevenue / streamerCount`) before the percentage or rate is applied.

**Weekly batch creation (`PayoutService::createWeeklyBatch`):**
- Calculates `week_start` (Monday) and `week_end` (Sunday)
- Creates the `WeeklyPayoutBatch`
- Bulk-updates all `payouts` where `weekly_payout_batch_id IS NULL`, `status=draft`, and show date falls in the range
- Calls `batch->recalculateTotal()` to sum all payout amounts into `total_payout`

**Finalizing (`PayoutService::finalizeBatch`):**
- Sets batch `status=finalized`, records `finalized_by` and `finalized_at`
- Bulk-updates all payouts in the batch to `status=approved`
- Only `draft` batches can be finalized (throws `RuntimeException` otherwise)

**To add a new payout type:**
1. Add the type key to `Streamer::payoutTypeLabels()`
2. Add any new rate columns to the `streamers` table via migration, `$fillable`, and `$casts`
3. Add the `case` in `PayoutService::computeStreamerPayout()`
4. Update `StreamerResource` form to show/hide the new rate field conditionally

---

## AI pipeline (Ollama)

**`OllamaService`** is the only class that talks to Ollama. It handles:
- `chat(string $prompt, string $action)` — returns the raw response string, logs to `ai_logs`
- `json(string $prompt, string $action = 'json')` — calls `chat()` then JSON-decodes; returns array
- `isAvailable()` — HEAD request to `/api/tags`, returns bool
- `availableModels()` — lists pulled models

Connection settings (read at call time, not at instantiation):

```php
config('ollama.base_url')   // or Setting::get('ollama_base_url')
config('ollama.model')      // or Setting::get('ollama_model')
config('ollama.timeout')    // or Setting::get('ollama_timeout')
```

The service reads from `Settings` table at runtime, falling back to `config/ollama.php`.

**`AiTitleParserService::parse(Show $show)`**
- Loads all active streamers' names
- Sends prompt: "Given streamer list X, which streamer likely hosted this show title? Return JSON `{streamer_name, confidence, reason}`"
- On success: updates `shows.ai_streamer_suggestion` (JSON) and `shows.status` stays `pending_review`
- On failure: logs error, no status change

**`AiInventoryMapperService::map(Show $show)`**
- Loads active inventory locations + their stock items for the show's primary streamer
- Builds a flat `$stockCatalogue` array to include in the prompt
- Sends prompt asking for a JSON mapping of sold units to inventory
- Expected response shape:
  ```json
  {
    "mapping_notes": "human-readable notes",
    "lines": [
      {
        "inventory_item_id": 1,
        "inventory_location_id": 2,
        "quantity_suggested": 3,
        "ai_confidence": "high|medium|low",
        "ai_reason": "why this item",
        "raw_description": "what the AI understood"
      }
    ]
  }
  ```
- On success: creates `DeductionRequest` + lines in a transaction, sets show `status=pending_approval`
- On failure: sets show back to `status=pending_review` so ops can retry

**To change the AI prompt:**
- `AiTitleParserService.php` line ~30 — the `$prompt` string
- `AiInventoryMapperService.php` line ~54 — the `$prompt` string
- Both prompts currently ask for strict JSON (`ONLY with valid JSON: ...`). If you change the response shape, update the parsing code after the `$this->ollama->json($prompt)` call.

---

## Queue jobs

All jobs implement `ShouldQueue` and use the `database` queue driver. All have `$tries = 1` — no automatic retries.

**Running the worker:**
```bash
php artisan queue:work
# or for development (restarts on code changes):
php artisan queue:listen
```

**Job table:** `jobs` (standard Laravel jobs table). Failed jobs go to `failed_jobs`.

**`ParseShowTitle`** — dispatched synchronously in `CreateShow::afterCreate()` if the show has a title.

**`MapShowInventory`** — dispatched from two places:
- `ShowResource::table()` — "Run AI Mapping" table action (visible only when `status=pending_review` and streamers are assigned)
- `ShowResource/Pages/ViewShow.php` — "Run AI Mapping" header action

**`NotifyShowReady`** — dispatched in `CreateShow::afterCreate()` every time.

**`NotifyShowReconciled`** — dispatched in `DeductionApprovalService::approve()` using `->afterCommit()` to ensure it fires only after the DB transaction commits.

**To add a new job:**
1. Create in `app/Jobs/`, implement `ShouldQueue`, inject the service you need via `handle(MyService $service)`
2. Dispatch with `MyJob::dispatch($id)` (use IDs, not model instances, to avoid serialization issues)
3. For jobs that must only fire after a DB commit, chain with `->afterCommit()`

---

## Filament resources — patterns used

### Eager loading (preventing N+1)

Every resource that displays related data in its table must override `getEloquentQuery()`:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with(['show', 'streamer']);
}
```

Currently implemented in: `PayoutResource`, `DeductionRequestResource`, `ShowResource`, `InventoryStockResource`, `InventoryMovementResource`, `WeeklyPayoutBatchResource`.

**Without this**, Filament fires one query per row per relation column. Add it any time you add a `TextColumn::make('relation.field')`.

### Pre-aggregated counts and sums

For columns like "Lines" and "Total COGS" in `DeductionRequestResource`:
- `TextColumn::make('lines_count')->counts('lines')` — Filament applies `withCount('lines')` automatically
- `TextColumn::make('lines_sum_line_total')` — requires `->withSum('lines', 'line_total')` in `getEloquentQuery()`

Never use `->getStateUsing(fn ($record) => $record->relation()->sum('col'))` in a list table — that fires one query per row.

### Streamer-scoped queries

`PayoutResource` and `InventoryLocationResource` apply row-level scoping for streamer users:

```php
$user = auth()->user();
if ($user->isStreamer() && !$user->isAdmin()) {
    $query->where('streamer_id', $user->streamer?->id);
}
```

Apply the same pattern to any new resource that streamers should see only their own data in.

### Approval pages (EditRecord extension)

`ViewDeductionRequest` extends `EditRecord` (not `ViewRecord`) so that form fields remain interactive for ops to edit before approving. Key details:
- `getFormActions()` returns `[]` to suppress the default Save button
- Header actions (Approve, Reject) call `$this->persistLines()` to write form state to the DB before executing the service
- `form()` uses `$this->data['field_name']` to read current form state — not `$this->record->field_name`
- Repeater `->default(...)` closure loads existing line data on page mount; Filament doesn't auto-bind repeater data from the model for custom data structures

### Disabled vs hidden fields

In `ViewDeductionRequest`, `$editable` is set based on `status`:
```php
$editable = !in_array($request->status, ['processed', 'rejected']);
```
Fields use `->disabled(!$editable)` — disabled fields are still visible and their values are still in `$this->data`. The repeater uses `->addable($editable)->deletable($editable)` to control whether lines can be added/removed.

---

## Roles & access control

**Package:** Spatie Laravel Permission v7

**Roles defined:** `super_admin`, `admin`, `streamer`

**Checked via model methods on `User`:**
```php
$user->isAdmin()       // hasRole('admin') || hasRole('super_admin')
$user->isStreamer()    // hasRole('streamer')
$user->isSuperAdmin()  // hasRole('super_admin')
```

**Filament gate:** `AdminPanelProvider` uses `->authMiddleware([Authenticate::class])`. Resource-level restrictions use:
- `canAccess()` — hides from nav and blocks the route entirely
- `canCreate()` / `canEdit()` / `canDelete()` — hides the action from UI

**Streamer profile link:** `users.streamer_id` (FK to `streamers`). Set via `UserResource` form. Used in `PayoutResource` and `InventoryLocationResource` to scope queries.

**Seed accounts:**
- `admin@vortexbreaks.com` / `password` — `admin` role
- `dev@vortexbreaks.com` / `devpassword` — `super_admin` role

**To add a new role:**
1. Add it to `DefaultDataSeeder::run()` via `Role::create(['name' => 'new_role'])`
2. Add a helper method to `User.php` (`isNewRole()`)
3. Add the role to any `canAccess()` checks as needed

---

## Notifications

**Type:** Laravel database notifications (stored in `notifications` table, not broadcast).

**Display:** Filament's built-in bell icon widget, polled every 30 seconds.

**Currently dispatched:**

| Notification | Trigger | Recipients |
|---|---|---|
| Low stock warning | After any stock mutation when `totalQuantity <= reorder_level` | All users |
| Items marked damaged | After `InventoryService::markDamaged()` | All users |
| Show ready for review | After `CreateShow::afterCreate()` (via `NotifyShowReady` job) | All users |
| Show reconciled | After deduction approved (via `NotifyShowReconciled` job) | All users |

**Sending to all users:**
```php
Notification::make()
    ->title('...')
    ->body('...')
    ->warning()
    ->sendToDatabase(User::all());
```

**To add a new notification:**
1. Create a notification class in `app/Notifications/` if it needs rich formatting, or use the Filament `Notification` facade inline
2. Dispatch via `sendToDatabase(User::all())` for broadcast-to-all, or target specific users
3. For notifications triggered by async events, put them in a `Job` dispatched `->afterCommit()` if inside a transaction

---

## Activity log

**Package:** Spatie Activitylog v5

**Models that log changes:** `InventoryItem`, `InventoryMovement`, `Show` (trait `LogsActivity` + `getActivitylogOptions()` returning `LogOptions::defaults()->logAll()->logOnlyDirty()`).

**Viewing:** `ActivityLogResource` at `/admin/activity-logs`. Admin-only. Clicking a row shows a before/after diff table for all changed attributes.

**To enable logging on a new model:**
```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MyModel extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }
}
```

---

## Branding & theming

Branding is driven by the `settings` table, applied at runtime in `AdminPanelProvider::boot()`.

**Primary color** — read from `Setting::get('primary_color')` on every request, cached 1 hour. Passed to Filament via `->colors(['primary' => Color::hex($color)])`. Takes effect on next page load.

**Logo** — uploaded to `storage/app/public/brand/`. Path stored in `settings`. Displayed in the sidebar via a custom `->brandLogo()` on the panel.

**Brand name** — fallback text when no logo is set, shown in the sidebar header.

**To change the primary color in code (not via UI):**
```php
Setting::set('primary_color', '#2563eb');
```

**To change the logo in code:**
```php
$path = $uploadedFile->store('brand', 'public');
Setting::set('logo_path', $path);
```

---

## Adding a new feature — checklist

When adding a new resource or workflow to the platform, work through this in order:

1. **Migration** — create the table, add FK constraints and indexes on columns used in `WHERE` clauses
2. **Model** — `$fillable`, `$casts`, relationships, any helper methods (`statusLabels()`, `isX()`)
3. **Service** — if the feature involves multi-step operations or transactions, put them in a service class
4. **Job** — if any work should run async (AI calls, notifications), create a job
5. **Resource** — create the Filament resource; add `getEloquentQuery()->with([...])` immediately if the table has relation columns
6. **Navigation** — assign to the correct `getNavigationGroup()` and `getNavigationSort()`
7. **Access control** — add `canAccess()`, `canCreate()`, etc. for streamer/admin scoping
8. **Seeder** — add demo data to `DemoDataSeeder` so `migrate:fresh --seed` produces a realistic state
9. **README** — update the data model diagram and project structure section
10. **This doc** — add a section here if the new feature has non-obvious behavior

**Naming conventions:**
- Services: `XxxService.php` in `app/Services/`
- Jobs: verb + noun, e.g. `MapShowInventory.php` in `app/Jobs/`
- Resources: `XxxResource.php` with pages in `app/Filament/Resources/XxxResource/Pages/`
- Models: singular PascalCase; table names are plural snake_case (Laravel default)

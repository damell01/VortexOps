<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class EndOfStreamForm extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $moduleSlug = 'streams';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-camera';
    protected static ?string $title = 'End of Stream';

    public ?Show $show = null;
    public int $step = 1;
    public ?string $lastSavedAt = null;

    public string $hoursStreamed = '';
    public string $shipments = '';
    public string $pweCount = '';
    public string $labelCount = '';
    public string $packagesOver500 = '';
    public string $logNotes = '';
    public bool $isSlowPack = false;
    public string $fulfillmentNotes = '';
    public string $search = '';
    public bool $showInventoryPicker = false;
    public bool $showManualItemForm = false;
    public string $manualName = '';
    public int $manualQuantity = 1;
    public string $manualDisposition = 'sold';
    public array $stagedQuantities = [];
    public string $pickerCategory = '';
    public bool $pickerStagedOnly = false;
    public int $pickerLimit = 60;

    public function getTitle(): string | Htmlable
    {
        return $this->show ? "Show Report: {$this->show->title}" : 'End of Stream';
    }

    public function getSubheading(): ?string
    {
        return 'Choose what was used during this show. Items can come directly from Main Warehouse inventory.';
    }

    public function getView(): string
    {
        return 'filament.pages.end-of-stream-items';
    }

    public function mount(?string $showId = null): void
    {
        $showId = $showId ?: request()->query('showId');
        if ($showId) {
            $this->show = Show::with(['streamers', 'channel'])->find($showId);
            $this->resumeDraft();
        }
    }

    public function selectShow(string $showId): void
    {
        $this->show = $showId !== '' ? Show::with(['streamers', 'channel'])->findOrFail($showId) : null;
        $this->search = '';
        $this->stagedQuantities = [];
        $this->showInventoryPicker = false;
        $this->showManualItemForm = false;
        $this->step = 1;
        $this->lastSavedAt = null;
        if ($this->show) $this->resumeDraft();
    }

    private function resumeDraft(): void
    {
        $entry = $this->logEntry();
        if (! $entry) return;
        $this->loadDetails();
        $this->lastSavedAt = $entry->draft_saved_at?->toIso8601String() ?? $entry->updated_at?->toIso8601String();
        $this->step = (! $entry->isSubmitted() || $entry->status === 'changes_requested')
            ? max(1, min(3, (int) ($entry->draft_step ?: 1))) : 3;
    }

    public function getShowsProperty()
    {
        $user = auth()->user();
        $query = Show::query()->inChannelContext()->where('status', '!=', 'closed')
            ->whereDate('show_date', '<=', today())->orderByDesc('show_date')->orderByDesc('start_time');

        if ($user?->isStreamer() && ! $user?->isAdmin() && ! $user?->isOwner()) {
            $streamerId = Streamer::where('user_id', $user->id)->value('id');
            if (! $streamerId) return collect();
            $query->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamerId));
        }

        return $query->limit(30)->get();
    }

    public function toggleBrowse(): void
    {
        $this->showInventoryPicker = ! $this->showInventoryPicker;
        if ($this->showInventoryPicker) $this->showManualItemForm = false;
    }

    public function scanIntoPicker(string $code): void
    {
        $code = trim($code);
        if ($code === '') return;
        $item = InventoryItem::findByScan($code);
        if (! $item) {
            Notification::make()->title('Nothing matches ' . $code)->body('No item carries that barcode, UPC or SKU.')->warning()->send();
            return;
        }
        if (! $this->inventoryScopeQuery()->whereKey($item->getKey())->exists()) {
            Notification::make()->title($item->name . ' is not available')->body('It has no stock in Main Warehouse or an inventory location available to this show.')->warning()->send();
            return;
        }
        $this->showInventoryPicker = true;
        $this->search = $item->name;
        $this->stageItem($item->getKey(), 1);
        Notification::make()->title('Added ' . $item->name)->body('Now ' . $this->stagedQuantities[$item->getKey()] . ' staged.')->success()->send();
    }

    public function stageItem(int $inventoryItemId, int $delta = 1): void
    {
        $this->setStagedQuantity($inventoryItemId, ($this->stagedQuantities[$inventoryItemId] ?? 0) + $delta);
    }

    public function setStagedQuantity(int $inventoryItemId, int $quantity): void
    {
        $quantity = max(0, $quantity);
        if ($quantity === 0) { unset($this->stagedQuantities[$inventoryItemId]); return; }
        $this->stagedQuantities[$inventoryItemId] = $quantity;
    }

    public function clearStaged(): void { $this->stagedQuantities = []; }

    public function getStagedSummaryProperty(): array
    {
        $staged = array_filter($this->stagedQuantities, fn ($qty) => $qty > 0);
        if (empty($staged)) return ['items' => 0, 'units' => 0, 'cost' => 0.0];
        $costs = InventoryItem::whereKey(array_keys($staged))->pluck('average_cost', 'id');
        $cost = 0.0;
        foreach ($staged as $itemId => $quantity) $cost += (float) ($costs[$itemId] ?? 0) * $quantity;
        return ['items' => count($staged), 'units' => array_sum($staged), 'cost' => round($cost, 2)];
    }

    public function addStagedItems(string $disposition = 'sold'): void
    {
        $staged = array_filter($this->stagedQuantities, fn ($qty) => $qty > 0);
        if (empty($staged)) return;
        foreach ($staged as $inventoryItemId => $quantity) $this->addLineItem((int) $inventoryItemId, (int) $quantity, $disposition);
        $this->stagedQuantities = [];
        $this->showInventoryPicker = false;
        $this->search = '';
        $count = count($staged);
        Notification::make()->title($count === 1 ? '1 item added to the report' : "{$count} items added to the report")->success()->send();
    }

    public function toggleManualItem(): void
    {
        $this->showManualItemForm = ! $this->showManualItemForm;
        if ($this->showManualItemForm) $this->showInventoryPicker = false;
    }

    private function reportStreamerId(): ?int
    {
        $entry = $this->show ? StreamerLogEntry::where('show_id', $this->show->id)->first() : null;
        if ($entry?->streamer_id) return (int) $entry->streamer_id;
        $authStreamer = Streamer::where('user_id', auth()->id())->value('id');
        if ($authStreamer) return (int) $authStreamer;
        return $this->show?->streamers()->value('streamers.id');
    }

    private function reportLocationIds()
    {
        $ids = collect();
        $streamerId = $this->reportStreamerId();
        if ($streamerId) {
            $ids = Streamer::find($streamerId)?->inventoryLocations()->pluck('inventory_locations.id') ?? collect();
        }

        // For the step-by-step rollout, streamers can draw directly from the
        // shared warehouse instead of requiring stock to be transferred into a
        // personal location first.
        $warehouseIds = InventoryLocation::query()
            ->where('status', 'active')
            ->where('type', 'main_storage')
            ->pluck('id');

        $defaultReceiving = InventoryLocation::defaultReceivingId();
        if ($defaultReceiving) $warehouseIds->push($defaultReceiving);

        return $ids->merge($warehouseIds)->filter()->unique()->values();
    }

    private function inventoryScopeQuery()
    {
        $locationIds = $this->reportLocationIds();
        $query = InventoryItem::query()->where('is_active', true);
        if ($locationIds->isNotEmpty()) {
            return $query->whereHas('stock', fn ($q) => $q->whereIn('inventory_location_id', $locationIds)->where('quantity', '>', 0))
                ->withSum(['stock as stock_sum_quantity' => fn ($q) => $q->whereIn('inventory_location_id', $locationIds)], 'quantity');
        }
        return $query->whereRaw('1 = 0');
    }

    private function inventoryQuery()
    {
        $query = $this->inventoryScopeQuery();
        if ($this->search !== '') {
            $needle = trim($this->search);
            $query->where(function ($q) use ($needle) {
                $q->where('name', 'like', "%{$needle}%")->orWhere('sku', 'like', "%{$needle}%")
                    ->orWhere('brand', 'like', "%{$needle}%")->orWhere('barcode', 'like', "%{$needle}%")
                    ->orWhere('upc', 'like', "%{$needle}%");
            });
        }
        if ($this->pickerCategory !== '') $query->where('category', $this->pickerCategory);
        if ($this->pickerStagedOnly) {
            $staged = array_keys(array_filter($this->stagedQuantities, fn ($qty) => $qty > 0));
            $query->whereKey($staged ?: [0]);
        }
        return $query->orderBy('name');
    }

    public function getInventoryTotalProperty(): int { return $this->inventoryQuery()->toBase()->getCountForPagination(); }
    public function getInventoryProperty() { return $this->inventoryQuery()->limit($this->pickerLimit)->get(); }

    public function getPickerCategoriesProperty(): array
    {
        $locationIds = $this->reportLocationIds();
        $query = InventoryItem::query()->where('is_active', true)->whereNotNull('category');
        if ($locationIds->isNotEmpty()) $query->whereHas('stock', fn ($q) => $q->whereIn('inventory_location_id', $locationIds)->where('quantity', '>', 0));
        return $query->distinct()->orderBy('category')->pluck('category')->all();
    }

    public function showMoreInventory(): void { $this->pickerLimit += 60; }
    public function updatedSearch(): void { $this->pickerLimit = 60; }
    public function updatedPickerCategory(): void { $this->pickerLimit = 60; }
    public function updatedPickerStagedOnly(): void { $this->pickerLimit = 60; }

    public function reportBlockedReason(): ?string
    {
        if (! $this->show || $this->reportStreamerId()) return null;
        return auth()->user()?->isAdmin()
            ? 'This show has no streamer assigned. Assign a streamer to the show first.'
            : 'This show has no streamer assigned, and your account is not linked to a streamer profile.';
    }

    public function logEntry(): ?StreamerLogEntry
    {
        if (! $this->show) return null;
        $streamerId = $this->reportStreamerId();
        if (! $streamerId) return null;
        return StreamerLogEntry::firstOrCreate(['show_id' => $this->show->id], ['streamer_id' => $streamerId, 'status' => 'pending']);
    }

    private function touchDraft(?int $step = null): void
    {
        $entry = $this->logEntry(); if (! $entry) return;
        $savedAt = now();
        StreamerLogEntry::whereKey($entry->id)->update(array_filter(['draft_step' => $step ?? $this->step, 'draft_saved_at' => $savedAt], fn ($value) => $value !== null));
        $this->lastSavedAt = $savedAt->toIso8601String();
    }

    public function getLineItemsProperty()
    {
        $entry = $this->logEntry();
        return $entry ? $entry->items()->with('inventoryItem')->orderBy('id')->get() : collect();
    }

    public function getSummaryProperty(): array
    {
        $lines = $this->lineItems;
        return [
            'items' => $lines->count(), 'units' => (int) $lines->sum('quantity'),
            'sold' => (int) $lines->where('disposition', 'sold')->sum('quantity'),
            'giveaway' => (int) $lines->where('disposition', 'giveaway')->sum('quantity'),
            'promo' => (int) $lines->where('disposition', 'promo')->sum('quantity'),
            'other' => (int) $lines->where('disposition', 'other')->sum('quantity'),
            'productCost' => (float) $lines->sum(fn ($line) => $line->total_cost),
            'giveawayCost' => (float) $lines->where('disposition', 'giveaway')->sum(fn ($line) => $line->total_cost),
            'unmatched' => $lines->whereNull('inventory_item_id')->count(),
        ];
    }

    public function getWhatnotReferenceProperty(): array
    {
        if (! $this->show) return [];
        return ['orders' => $this->show->units_sold, 'sales' => $this->show->gross_revenue, 'earnings' => $this->show->completed_earnings,
            'buyers' => $this->show->buyers_count, 'giveaways' => $this->show->giveaways_count,
            'shipments' => $this->show->shipments()->count(), 'analytics_synced_at' => $this->show->last_analytics_synced_at];
    }

    public function addLineItem(int $inventoryItemId, int $quantity = 1, string $disposition = 'sold'): void
    {
        $entry = $this->logEntry(); $item = InventoryItem::find($inventoryItemId); if (! $entry || ! $item) return;
        $disposition = array_key_exists($disposition, StreamerLogItem::DISPOSITIONS) ? $disposition : 'sold';
        $line = $entry->items()->where('inventory_item_id', $item->id)->where('disposition', $disposition)->first();
        if ($line) $line->increment('quantity', max(1, $quantity));
        else $entry->items()->create(['inventory_item_id' => $item->id, 'item_name' => $item->name, 'quantity' => max(1, $quantity), 'disposition' => $disposition, 'unit_cost' => null]);
        $this->syncProductCost(); $this->touchDraft();
    }

    public function addManualLineItem(string $name, int $quantity = 1, ?float $unitCost = null, string $disposition = 'sold'): void
    {
        $entry = $this->logEntry(); $name = trim($name); if (! $entry || $name === '') return;
        $disposition = array_key_exists($disposition, StreamerLogItem::DISPOSITIONS) ? $disposition : 'sold';
        $entry->items()->create(['inventory_item_id' => null, 'item_name' => $name, 'quantity' => max(1, $quantity), 'disposition' => $disposition, 'unit_cost' => $unitCost]);
        $this->syncProductCost(); $this->touchDraft();
    }

    public function addManualItemFromForm(): void
    {
        $name = trim($this->manualName);
        if ($name === '') { Notification::make()->title('Enter an item name')->warning()->send(); return; }
        $this->addManualLineItem($name, max(1, $this->manualQuantity), null, $this->manualDisposition);
        $this->manualName = ''; $this->manualQuantity = 1; $this->manualDisposition = 'sold'; $this->showManualItemForm = false;
    }

    public function setLineQuantity(int $lineId, int $quantity): void { $this->logEntry()?->items()->find($lineId)?->update(['quantity' => max(1, $quantity)]); $this->syncProductCost(); $this->touchDraft(); }
    public function setLineCost(int $lineId, ?float $unitCost): void
    {
        $line = $this->logEntry()?->items()->find($lineId); if (! $line) return;
        $line->update(['unit_cost' => ($unitCost !== null && $unitCost > 0) ? $unitCost : null]); $this->syncProductCost(); $this->touchDraft();
    }
    public function clearLineCost(int $lineId): void { $this->setLineCost($lineId, null); }

    public function setLineDisposition(int $lineId, string $disposition): void
    {
        if (! array_key_exists($disposition, StreamerLogItem::DISPOSITIONS)) return;
        $entry = $this->logEntry(); $line = $entry?->items()->find($lineId); if (! $entry || ! $line) return;
        $existing = $entry->items()->whereKeyNot($line->id)->where('inventory_item_id', $line->inventory_item_id)->where('item_name', $line->item_name)->where('disposition', $disposition)->first();
        if ($existing) { $existing->increment('quantity', $line->quantity); $line->delete(); } else $line->update(['disposition' => $disposition]);
        $this->touchDraft();
    }

    public function removeLineItem(int $lineId): void { $this->logEntry()?->items()->find($lineId)?->delete(); $this->syncProductCost(); $this->touchDraft(); }

    private function syncProductCost(): void
    {
        $entry = $this->logEntry(); if (! $entry) return;
        $cost = $entry->items()->with('inventoryItem')->get()->sum(fn (StreamerLogItem $line) => $line->total_cost);
        StreamerLogEntry::whereKey($entry->id)->update(['product_cost' => round((float) $cost, 2)]);
    }

    private function suggestedHours(): ?float { $minutes = (int) ($this->show?->show_duration ?? 0); return $minutes > 0 ? round($minutes / 60, 2) : null; }
    private function suggestedShipments(): ?int { $count = (int) ($this->show?->shipments()->count() ?? 0); return $count > 0 ? $count : null; }

    public function loadDetails(): void
    {
        $entry = $this->logEntry(); if (! $entry) return;
        $this->hoursStreamed = (string) ($entry->hours_streamed ?? $this->suggestedHours() ?? '');
        $this->shipments = (string) ($entry->number_of_shipments ?? $this->suggestedShipments() ?? '');
        $fill = array_filter([
            'hours_streamed' => $entry->hours_streamed === null && $this->hoursStreamed !== '' ? (float) $this->hoursStreamed : null,
            'number_of_shipments' => $entry->number_of_shipments === null && $this->shipments !== '' ? (int) $this->shipments : null,
        ], fn ($value) => $value !== null);
        if ($fill !== []) StreamerLogEntry::whereKey($entry->id)->update($fill);
        $this->pweCount = (string) ($entry->pwe_count ?? ''); $this->labelCount = (string) ($entry->label_count ?? '');
        $this->packagesOver500 = (string) ($entry->number_of_packages_over_500 ?? ''); $this->logNotes = (string) ($entry->notes ?? '');
        $this->isSlowPack = (bool) ($this->show?->is_slow_pack ?? false); $this->fulfillmentNotes = (string) ($this->show?->fulfillment_notes ?? '');
    }

    public function saveDetails(): void
    {
        $entry = $this->logEntry(); if (! $entry) return; $savedAt = now();
        $entry->update([
            'hours_streamed' => $this->hoursStreamed !== '' ? (float) $this->hoursStreamed : null,
            'number_of_shipments' => $this->shipments !== '' ? (int) $this->shipments : null,
            'pwe_count' => $this->pweCount !== '' ? (int) $this->pweCount : null,
            'label_count' => $this->labelCount !== '' ? (int) $this->labelCount : null,
            'number_of_packages_over_500' => $this->packagesOver500 !== '' ? (int) $this->packagesOver500 : null,
            'notes' => $this->logNotes !== '' ? $this->logNotes : null,
        ]);
        $this->show?->forceFill(['is_slow_pack' => $this->isSlowPack, 'fulfillment_notes' => $this->fulfillmentNotes !== '' ? $this->fulfillmentNotes : null])->save();
        StreamerLogEntry::whereKey($entry->id)->update(['draft_step' => $this->step, 'draft_saved_at' => $savedAt]);
        $this->lastSavedAt = $savedAt->toIso8601String();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['hoursStreamed', 'shipments', 'pweCount', 'labelCount', 'packagesOver500', 'logNotes'], true)) $this->saveDetails();
    }

    public function goToStep(int $step): void { $step = max(1, min(3, $step)); $this->saveDetails(); $this->step = $step; $this->touchDraft($step); }

    public function getDeductionPreviewProperty(): array
    {
        $entry = $this->logEntry(); if (! $entry) return [];
        $problems = []; $locationIds = $this->reportLocationIds();
        foreach ($entry->items()->with('inventoryItem')->get() as $line) {
            if (! $line->inventoryItem) { $problems[] = "\"{$line->item_name}\" is not linked to an inventory product."; continue; }
            $onHand = InventoryStock::where('inventory_item_id', $line->inventory_item_id)->whereIn('inventory_location_id', $locationIds)->sum('quantity');
            if ((float) $onHand < (int) $line->quantity) $problems[] = "\"{$line->item_name}\" needs {$line->quantity} but only " . (float) $onHand . ' is available in Main Warehouse / show inventory.';
        }
        return $problems;
    }

    public function submit(): void
    {
        if (! $this->show) { Notification::make()->title('No show selected')->body('Please select a show first.')->danger()->send(); return; }
        $entry = $this->logEntry(); if (! $entry) { Notification::make()->title('Could not open the report for this show')->danger()->send(); return; }
        $lines = $entry->items()->with('inventoryItem')->get();
        if ($lines->isEmpty()) { Notification::make()->title('No items added')->body('Add at least one item used during this show.')->warning()->send(); return; }
        try {
            $this->saveDetails();
            foreach ($lines as $line) if ($line->costIsFromInventory()) $line->forceFill(['unit_cost' => $line->effectiveUnitCost()])->save();
            $entry->update(['product_cost' => round((float) $lines->sum(fn ($line) => $line->total_cost), 2), 'status' => 'streamer_reviewed']);
            $problems = $entry->submitReport(); $entry->refresh();
            $postingPolicy = (string) Setting::get('show_inventory_posting_policy', 'on_submit');
            StreamerLogEntry::whereKey($entry->id)->update(['draft_step' => 3, 'draft_saved_at' => now()]);
            if (empty($problems)) {
                Notification::make()->title('Show report submitted')->body($postingPolicy === 'on_submit'
                    ? $this->summary['units'] . ' units reported and deducted from inventory.' : $this->summary['units'] . ' units reported.')->success()->send();
            } else {
                Notification::make()->title('Show report submitted with inventory issues')->body(implode("\n", $problems))->warning()->duration(12000)->send();
            }
            if ($entry->status !== 'admin_approved') {
                $unmatched = $entry->items()->whereNull('inventory_item_id')->count();
                $problemCount = max($unmatched, count($problems));
                $admins = User::query()->get()->filter(fn (User $user) => $user->isAdmin() || $user->isOwner());
                if ($admins->isNotEmpty()) {
                    $notification = Notification::make()->title($problemCount > 0 ? 'Show report needs review' : 'Show report submitted')
                        ->body($problemCount > 0 ? "{$this->show->title}: {$problemCount} reconciliation issue(s) need attention." : "{$this->show->title} is ready for admin approval.")->warning();
                    $notification->sendToDatabase($admins);
                }
            }
            $this->lastSavedAt = now()->toIso8601String(); $this->step = 3; $this->stagedQuantities = [];
        } catch (\Throwable $e) {
            report($e); Notification::make()->title('Could not submit the report')->body($e->getMessage())->danger()->send();
        }
    }

    public static function canAccess(): bool
    {
        if (\App\Support\RoleAccess::grants(static::class)) return true;
        $user = auth()->user();
        return AdminModules::isEnabled('streams') && (($user?->isStreamer() ?? false) || ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false));
    }

    public static function getSlug(?Panel $panel = null): string { return 'end-of-stream'; }
    public static function shouldRegisterNavigation(): bool
    {
        if (\App\Support\RoleAccess::grants(static::class)) return true;
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) return false;
        return auth()->user()?->isStreamer() ?? false;
    }
    public static function getNavigationLabel(): string { return 'End of Stream'; }
    public static function getNavigationGroup(): string | null { return 'Streams'; }
    public static function getNavigationSort(): ?int { return 38; }
}

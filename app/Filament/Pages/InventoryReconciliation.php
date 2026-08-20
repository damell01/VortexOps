<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Support\AdminModules;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Support\NavVisibility;

class InventoryReconciliation extends Page implements HasForms
{
    use HasModuleAccess, InteractsWithForms;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Inventory Reconciliation';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-check-circle';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Reconciliation';
    }

    public static function getNavigationSort(): ?int
    {
        return 999;
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-reconciliation';
    }

    public function getSubheading(): ?string
    {
        return 'Count physical inventory and reconcile with system records';
    }

    // ── State ──────────────────────────────────────────────────────────────

    public ?int $selectedLocationId = null;
    public ?int $selectedItemId = null;
    public string $physicalCount = '';
    public string $reconciliationReason = '';
    public array $reconciliationResults = [];
    public bool $showResults = false;

    // ── Computed Properties ────────────────────────────────────────────────

    public function getLocationsProperty()
    {
        return InventoryLocation::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    public function getItemsProperty()
    {
        return InventoryItem::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'average_cost']);
    }

    public function getLocationStockProperty(): array
    {
        if (!$this->selectedLocationId) {
            return [];
        }

        $stock = InventoryStock::where('inventory_location_id', $this->selectedLocationId)
            ->with('item')
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => [
                'item_id' => $s->inventory_item_id,
                'name' => $s->item?->name,
                'sku' => $s->item?->sku,
                'system_qty' => (float) $s->quantity,
                'avg_cost' => (float) $s->item?->average_cost ?? 0,
                'value' => (float) $s->quantity * (float) ($s->item?->average_cost ?? 0),
            ])
            ->toArray();

        return $stock;
    }

    // ── Actions ────────────────────────────────────────────────────────────

    public function reconcileItem(): void
    {
        if (!$this->selectedItemId || $this->physicalCount === '') {
            Notification::make()
                ->title('Error')
                ->body('Select item and enter physical count')
                ->danger()
                ->send();
            return;
        }

        $item = InventoryItem::find($this->selectedItemId);
        if (!$item) {
            return;
        }

        $physicalQty = (float) $this->physicalCount;
        $location = InventoryLocation::find($this->selectedLocationId);

        $stock = InventoryStock::where([
            'inventory_item_id' => $this->selectedItemId,
            'inventory_location_id' => $this->selectedLocationId,
        ])->first();

        $systemQty = $stock ? (float) $stock->quantity : 0;
        $variance = $physicalQty - $systemQty;
        $variancePct = $systemQty > 0 ? ($variance / $systemQty) * 100 : 0;

        if ($variance != 0) {
            // A stocktake is an adjustment to an exact figure, which is what
            // adjustStock does — so it goes through there rather than writing
            // the movement and the stock row as two separate statements. The
            // hand-written pair had no transaction between them, no
            // before/after, and to_location_id set even when the count came
            // down, which recorded a shortfall as stock arriving.
            app(\App\Services\InventoryService::class)->adjustStock(
                \App\Models\InventoryItem::findOrFail($this->selectedItemId),
                \App\Models\InventoryLocation::findOrFail($this->selectedLocationId),
                (float) $physicalQty,
                $this->reconciliationReason ?: 'Inventory reconciliation',
                'reconciliation',
            );

            Notification::make()
                ->title('Item Reconciled')
                ->body("Variance: {$variance} units ({$variancePct}%)")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('No Variance')
                ->body('Physical count matches system.')
                ->success()
                ->send();
        }

        $this->physicalCount = '';
        $this->reconciliationReason = '';
        $this->selectedItemId = null;
    }

    public function reconcileLocation(): void
    {
        if (!$this->selectedLocationId) {
            return;
        }

        $results = [];
        $totalSystemValue = 0;
        $totalPhysicalValue = 0;
        $totalVariance = 0;

        foreach ($this->getLocationStockProperty() as $stock) {
            $totalSystemValue += $stock['value'];

            $results[] = [
                'item_name' => $stock['name'],
                'sku' => $stock['sku'],
                'system_qty' => $stock['system_qty'],
                'avg_cost' => $stock['avg_cost'],
                'system_value' => $stock['value'],
                'needs_count' => true,
            ];
        }

        $this->reconciliationResults = $results;
        $this->showResults = true;

        Notification::make()
            ->title('Location Ready for Count')
            ->body(count($results) . ' items to count')
            ->info()
            ->send();
    }
}

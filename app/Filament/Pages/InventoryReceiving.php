<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryContainer;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class InventoryReceiving extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Receiving Workflow';

    protected static ?string $slug = 'inventory-receiving';

    public ?int $inventory_item_id = null;

    public ?int $inventory_location_id = null;

    public string $container_type = 'pallet';

    public string $label = '';

    public string $barcode = '';

    public string $quantity = '0';

    public bool $scanner_ready = true;

    public string $seller_unit_cost = '';

    public string $shipping_unit_cost = '';

    public string $other_unit_fees = '';

    public string $average_unit_cost = '';

    public string $cost_notes = '';

    public string $receipt_notes = '';

    public function getView(): string
    {
        return 'filament.pages.inventory-receiving';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationLabel(): string
    {
        return 'Receiving';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-truck';
    }

    public function mount(): void
    {
        $this->inventory_location_id = InventoryLocation::query()
            ->where('type', 'receiving')
            ->where('status', 'active')
            ->value('id');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('breakdown')
                ->label('Pallet Breakdown')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->url(InventoryBreakdown::getUrl()),
            Action::make('putaway')
                ->label('Container Putaway')
                ->icon('heroicon-o-map')
                ->color('gray')
                ->url(InventoryPutaway::getUrl()),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function itemOptions(): array
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (InventoryItem $item): array => [
                $item->id => trim(($item->sku ? $item->sku . ' - ' : '') . $item->name),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function receivingLocationOptions(): array
    {
        return InventoryLocation::query()
            ->where('status', 'active')
            ->whereIn('type', ['receiving', 'main_storage', 'fulfillment'])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, InventoryContainer>
     */
    public function recentReceipts()
    {
        return InventoryContainer::query()
            ->with(['item:id,name,sku', 'location:id,name'])
            ->latest('id')
            ->limit(6)
            ->get();
    }

    public function selectedItem(): ?InventoryItem
    {
        if (! $this->inventory_item_id) {
            return null;
        }

        return InventoryItem::find($this->inventory_item_id);
    }

    public function receiveInventory(): void
    {
        $validated = $this->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'inventory_location_id' => ['required', 'exists:inventory_locations,id'],
            'container_type' => ['required', 'in:pallet,case,box,unit,other'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'seller_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'shipping_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'other_unit_fees' => ['nullable', 'numeric', 'min:0'],
            'average_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'receipt_notes' => ['nullable', 'string'],
            'cost_notes' => ['nullable', 'string'],
        ]);

        $item = InventoryItem::findOrFail($validated['inventory_item_id']);
        $location = InventoryLocation::findOrFail($validated['inventory_location_id']);
        $label = trim($validated['label'] ?: sprintf(
            '%s-%s',
            strtoupper(substr($validated['container_type'], 0, 3)),
            now()->format('mdHis'),
        ));

        $container = app(InventoryService::class)->receiveIntoContainer(
            $item,
            $location,
            (float) $validated['quantity'],
            [
                'container_type' => $validated['container_type'],
                'label' => $label,
                'barcode' => $validated['barcode'] ?: null,
                'scanner_ready' => $this->scanner_ready,
                'notes' => $validated['receipt_notes'] ?: null,
            ],
            [
                'barcode' => $validated['barcode'] ?: null,
                'seller_unit_cost' => $validated['seller_unit_cost'] !== '' ? (float) $validated['seller_unit_cost'] : null,
                'shipping_unit_cost' => $validated['shipping_unit_cost'] !== '' ? (float) $validated['shipping_unit_cost'] : null,
                'other_unit_fees' => $validated['other_unit_fees'] !== '' ? (float) $validated['other_unit_fees'] : null,
                'average_unit_cost' => $validated['average_unit_cost'] !== '' ? (float) $validated['average_unit_cost'] : null,
                'cost_notes' => $validated['cost_notes'] ?: null,
            ],
            $validated['receipt_notes'] ?: null,
        );

        $this->label = '';
        $this->barcode = '';
        $this->quantity = '0';
        $this->receipt_notes = '';

        Notification::make()
            ->title('Inventory received')
            ->body("Created {$container->container_type} {$container->label} with {$container->quantity} units in {$location->name}.")
            ->success()
            ->send();
    }
}

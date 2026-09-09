<?php

namespace App\Livewire;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Vendor;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Livewire\Component;

class InventoryQuickStockModal extends Component
{
    public bool $open = false;
    public ?int $productId = null;
    public string $productName = '';
    public ?int $locationId = null;
    public string $quantity = '1';
    public ?int $vendorId = null;
    public string $unitCost = '';
    public string $reason = '';

    public function openForProduct(int $productId): void
    {
        $record = InventoryItem::find($productId);

        if (! $record || ! InventoryItemResource::canEdit($record)) {
            Notification::make()
                ->title('Unable to add stock')
                ->body('This item is unavailable or you do not have permission to edit it.')
                ->danger()
                ->send();
            return;
        }

        $this->resetValidation();
        $this->productId = $record->getKey();
        $this->productName = $record->name;
        $this->locationId = $this->defaultLocationId();
        $this->quantity = '1';
        $this->vendorId = null;
        $this->unitCost = '';
        $this->reason = '';
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'productId' => ['required', 'integer'],
            'locationId' => ['required', 'integer', 'exists:inventory_locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'vendorId' => ['nullable', 'integer'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $record = InventoryItem::findOrFail((int) $data['productId']);
        abort_unless(InventoryItemResource::canEdit($record), 403);

        $location = InventoryLocation::findOrFail((int) $data['locationId']);
        $reason = trim((string) ($data['reason'] ?? ''));

        if (! empty($data['vendorId'])) {
            $vendor = Vendor::find((int) $data['vendorId']);
            $reason = ($reason !== '' ? $reason . ' — ' : '') . 'From ' . ($vendor?->name ?? 'Unknown Vendor');
        }

        app(InventoryService::class)->addStock(
            $record,
            $location,
            (float) $data['quantity'],
            'opening',
            $reason !== '' ? $reason : null,
            $data['unitCost'] !== null && $data['unitCost'] !== '' ? (float) $data['unitCost'] : null,
        );

        $qty = (float) $data['quantity'];
        $this->open = false;

        Notification::make()
            ->title('Stock added successfully')
            ->body(number_format($qty) . ' added to ' . $record->name . ' at ' . $location->name . '.')
            ->success()
            ->send();

        $this->dispatch('inventory-stock-added', productId: $record->getKey());
    }

    private function defaultLocationId(): ?int
    {
        $id = InventoryLocation::query()
            ->where('status', 'active')
            ->where('type', 'main_storage')
            ->orderBy('id')
            ->value('id');

        $id ??= InventoryLocation::query()
            ->where('status', 'active')
            ->where('name', 'Main Warehouse')
            ->value('id');

        $id ??= InventoryLocation::defaultReceivingId();

        return $id ? (int) $id : null;
    }

    public function render()
    {
        return view('livewire.inventory-quick-stock-modal', [
            'locations' => InventoryLocation::activeOptions(),
            'vendors' => Vendor::activeOptions(),
        ]);
    }
}

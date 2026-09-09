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
    public string $currentBarcode = '';
    public string $barcode = '';

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
        $this->currentBarcode = trim((string) $record->barcode);
        $this->barcode = $this->currentBarcode;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetValidation();
    }

    public function startBarcodeScan(): void
    {
        if (! $this->open || ! $this->productId) return;

        $this->dispatch(
            'open-camera-scanner',
            title: filled($this->currentBarcode) ? 'Replace item barcode' : 'Attach item barcode',
            helper: $this->productName,
        );
    }

    public function captureBarcode(string $value): void
    {
        if (! $this->open || ! $this->productId) return;

        $value = trim($value);
        if ($value === '') return;

        $clash = InventoryItem::query()
            ->whereKeyNot($this->productId)
            ->where(function ($query) use ($value) {
                $query->where('barcode', $value)
                    ->orWhere('upc', $value)
                    ->orWhere('sku', $value);
            })
            ->first();

        if ($clash) {
            Notification::make()
                ->title('Barcode already in use')
                ->body($value . ' is already assigned to "' . $clash->name . '". Nothing was changed.')
                ->danger()
                ->send();
            return;
        }

        $this->barcode = $value;
        $this->resetValidation('barcode');

        Notification::make()
            ->title(filled($this->currentBarcode) ? 'Replacement barcode scanned' : 'Barcode scanned')
            ->body($value . ' will be saved with this stock update.')
            ->success()
            ->send();
    }

    public function clearBarcode(): void
    {
        $this->barcode = '';
        $this->resetValidation('barcode');
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
            'barcode' => ['nullable', 'string', 'max:255'],
        ]);

        $record = InventoryItem::findOrFail((int) $data['productId']);
        abort_unless(InventoryItemResource::canEdit($record), 403);

        $barcode = trim((string) ($data['barcode'] ?? ''));
        if ($barcode !== '') {
            $clash = InventoryItem::query()
                ->whereKeyNot($record->getKey())
                ->where(function ($query) use ($barcode) {
                    $query->where('barcode', $barcode)
                        ->orWhere('upc', $barcode)
                        ->orWhere('sku', $barcode);
                })
                ->first();

            if ($clash) {
                $this->addError('barcode', $barcode . ' is already assigned to ' . $clash->name . '.');
                return;
            }
        }

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

        if ($barcode !== '' && $barcode !== trim((string) $record->barcode)) {
            $record->forceFill(['barcode' => $barcode])->save();
        }

        $qty = (float) $data['quantity'];
        $this->open = false;

        Notification::make()
            ->title('Stock added successfully')
            ->body(number_format($qty) . ' added to ' . $record->name . ' at ' . $location->name . ($barcode !== '' ? ' · Barcode ' . $barcode : '') . '.')
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

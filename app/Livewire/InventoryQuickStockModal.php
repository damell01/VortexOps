<?php

namespace App\Livewire;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\ProductIdentity;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use App\Models\Vendor;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Livewire\Component;

class InventoryQuickStockModal extends Component
{
    use WithFileUploads;

    public bool $open = false;
    public $itemPhoto = null;
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
        $this->barcode = '';
        $this->itemPhoto = null;
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
            title: 'Add another item barcode',
            helper: $this->productName,
        );
    }

    public function captureBarcode(string $value): void
    {
        if (! $this->open || ! $this->productId) return;

        $value = trim($value);
        if ($value === '') return;

        $clash = InventoryItem::withTrashed()
            ->whereKeyNot($this->productId)
            ->where(function ($query) use ($value) {
                $query->where('barcode', $value)
                    ->orWhere('upc', $value)
                    ->orWhere('sku', $value);
            })
            ->first();

        if ($clash || ProductIdentity::where('value', $value)->whereIn('type', ProductIdentity::SCANNABLE_TYPES)->where('product_id', '!=', $this->productId)->exists()) {
            Notification::make()
                ->title('Barcode already in use')
                ->body($value . ' is already assigned to another product. Nothing was changed.')
                ->danger()
                ->send();
            return;
        }

        $this->barcode = $value;
        $this->resetValidation('barcode');

        Notification::make()
            ->title('Additional barcode scanned')
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
            'barcode' => ['nullable', 'string', 'max:191'],
            'itemPhoto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $record = InventoryItem::findOrFail((int) $data['productId']);
        abort_unless(InventoryItemResource::canEdit($record), 403);

        $barcode = trim((string) ($data['barcode'] ?? ''));
        if ($barcode !== '') {
            $clash = InventoryItem::withTrashed()
                ->whereKeyNot($record->getKey())
                ->where(function ($query) use ($barcode) {
                    $query->where('barcode', $barcode)
                        ->orWhere('upc', $barcode)
                        ->orWhere('sku', $barcode);
                })
                ->first();

            if ($clash || ProductIdentity::where('value', $barcode)->whereIn('type', ProductIdentity::SCANNABLE_TYPES)->where('product_id', '!=', $record->getKey())->exists()) {
                $this->addError('barcode', $barcode . ' is already assigned to another product.');
                return;
            }
        }

        $location = InventoryLocation::findOrFail((int) $data['locationId']);
        \App\Support\StockGate::authorize($location);
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

        if ($barcode !== '' && ! in_array($barcode, array_filter([$record->barcode, $record->upc, $record->sku]), true)) {
            ProductIdentity::firstOrCreate([
                'product_id' => $record->getKey(),
                'type' => ProductIdentity::TYPE_BARCODE,
                'value' => $barcode,
            ]);
        }

        if ($this->itemPhoto) {
            try {
                $path = $this->itemPhoto->store('inventory-items', Product::IMAGE_DISK);
                if (! $path) throw new \RuntimeException('Photo upload failed.');
                $record->update(['image_path' => $path]);
            } catch (\Throwable $e) {
                report($e);
                Notification::make()->title('Stock saved, photo upload failed')->warning()->send();
            }
        }
        $this->itemPhoto = null;

        $qty = (float) $data['quantity'];
        $this->open = false;

        Notification::make()
            ->title('Stock added successfully')
            ->body(number_format($qty) . ' added to ' . $record->name . ' at ' . $location->name . ($barcode !== '' ? ' · Added barcode ' . $barcode : '') . '.')
            ->success()
            ->send();

        $this->dispatch('inventory-stock-added', productId: $record->getKey());
    }

    private function defaultLocationId(): ?int
    {
        $changeable = \App\Support\StockGate::changeableIds(auth()->user());
        $id = InventoryLocation::query()
            ->where('status', 'active')
            ->whereIn('id', $changeable)
            ->where('type', 'main_storage')
            ->orderBy('id')
            ->value('id');

        $id ??= InventoryLocation::query()
            ->where('status', 'active')
            ->where('name', 'Main Warehouse')
            ->value('id');

        $id ??= InventoryLocation::defaultReceivingId();
        $id = in_array((int) $id, $changeable, true) ? $id : ($changeable[0] ?? null);

        return $id ? (int) $id : null;
    }

    public function render()
    {
        return view('livewire.inventory-quick-stock-modal', [
            'locations' => \App\Support\StockGate::filter(InventoryLocation::activeOptions()),
            'vendors' => Vendor::activeOptions(),
        ]);
    }
}

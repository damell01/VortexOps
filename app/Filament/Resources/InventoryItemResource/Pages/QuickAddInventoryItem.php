<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\Rule;

class QuickAddInventoryItem extends Page
{
    protected static string $resource = InventoryItemResource::class;

    public ?array $data = [];

    public function getView(): string
    {
        return 'filament.pages.quick-add-inventory-item';
    }

    public function getTitle(): string
    {
        return 'Quick Add';
    }

    public function getSubheading(): ?string
    {
        return 'Create a simple inventory item and optionally put stock into a location now.';
    }

    public function mount(): void
    {
        $this->authorize('create', InventoryItem::class);
        $this->resetQuickAdd();
    }

    public function setScannedBarcode(string $barcode): void
    {
        $this->data['barcode'] = trim($barcode);
    }

    protected function defaultLocationId(): ?int
    {
        $user = auth()->user();

        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            return $user->streamer?->inventoryLocations()->value('inventory_locations.id');
        }

        return null;
    }

    public function resetQuickAdd(): void
    {
        $this->data = [
            'name' => '',
            'barcode' => '',
            'sku' => '',
            'category' => '',
            'unit_cost' => null,
            'preferred_vendor_id' => null,
            'location_id' => $this->defaultLocationId(),
            'quantity' => 1,
            'cost' => null,
        ];
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    protected function validated(): array
    {
        $data = validator($this->data ?? [], [
            'name'                => ['required', 'string', 'max:255'],
            'sku'                 => ['nullable', 'string', 'max:100', Rule::unique(InventoryItem::class, 'sku')],
            'barcode'             => ['nullable', 'string', 'max:100', Rule::unique(InventoryItem::class, 'barcode')],
            'category'            => ['nullable', 'string', 'max:255'],
            'unit_cost'           => ['nullable', 'numeric', 'min:0'],
            'preferred_vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'location_id'         => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'quantity'            => ['nullable', 'numeric', 'min:0'],
            'cost'                => ['nullable', 'numeric', 'min:0'],
        ], [
            'sku.unique'     => 'That SKU is already used by another item.',
            'barcode.unique' => 'That barcode already belongs to another item. Search or scan it instead of creating a duplicate.',
        ])->validate();

        foreach (['sku', 'barcode', 'category', 'unit_cost', 'cost', 'quantity', 'preferred_vendor_id', 'location_id'] as $blankable) {
            if (($data[$blankable] ?? null) === '') {
                $data[$blankable] = null;
            }
        }

        return $data;
    }

    public function submit(bool $addAnother = false): void
    {
        try {
            $validatedData = $this->validated();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // The toast is worth keeping — on a phone the offending field is
            // often scrolled off screen — but swallowing the exception here
            // meant nothing was ever highlighted, while the toast said to go
            // and check the highlighted fields. Re-throwing lets Livewire fill
            // the error bag so the fields actually mark themselves.
            Notification::make()
                ->title('Check the highlighted fields')
                ->body(collect($e->errors())->flatten()->join(' '))
                ->danger()
                ->send();

            throw $e;
        }

        try {
            $item = InventoryItem::create([
                'name' => $validatedData['name'],
                'sku' => $validatedData['sku'] ?? null,
                'barcode' => $validatedData['barcode'] ?? null,
                'category' => $validatedData['category'] ?? null,
                'unit_cost' => (float) ($validatedData['unit_cost'] ?? 0),
                'average_cost' => (float) ($validatedData['unit_cost'] ?? 0),
                'preferred_vendor_id' => $validatedData['preferred_vendor_id'] ?? null,
                'is_active' => true,
            ]);

            if (($validatedData['location_id'] ?? null) && (float) ($validatedData['quantity'] ?? 0) > 0) {
                $location = InventoryLocation::findOrFail($validatedData['location_id']);
                app(InventoryService::class)->addStock(
                    $item,
                    $location,
                    (float) $validatedData['quantity'],
                    'opening',
                    'Initial stock added via Quick Add',
                    (float) ($validatedData['cost'] ?? $validatedData['unit_cost'] ?? 0),
                );
            }

            Notification::make()
                ->title('Item added')
                ->body((float) ($validatedData['quantity'] ?? 0) > 0 ? 'Product and starting stock were saved.' : 'Product was saved with no starting stock.')
                ->success()
                ->send();

            if ($addAnother) {
                $this->resetQuickAdd();
                $this->dispatch('quick-add-ready');
                return;
            }

            $this->redirect(InventoryItemResource::getUrl('view', ['record' => $item]));
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Could not add item')
                ->body('Nothing was changed. Check the fields and try again.')
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

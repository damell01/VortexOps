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

    public int $currentStep = 1;

    public ?InventoryItem $createdItem = null;

    public function getView(): string
    {
        return 'filament.pages.quick-add-inventory-item';
    }

    public function mount(): void
    {
        $this->authorize('create', InventoryItem::class);

        // Auto-select streamer's location if they're a streamer
        $user = auth()->user();
        if ($user?->isStreamer() && !$user->isAdmin() && !$user->isOwner()) {
            $streamer = $user->streamer;
            if ($streamer) {
                $location = $streamer->inventoryLocations()->first();
                if ($location) {
                    $this->data['location_id'] = $location->id;
                }
            }
        }
    }

    public function scanBarcode(): void
    {
        Notification::make()
            ->title('Barcode Scanner')
            ->body('Camera barcode scanner coming soon. For now, enter the barcode manually.')
            ->info()
            ->send();
    }

    #[\Livewire\Attributes\On('next-step')]
    public function nextStep(): void
    {
        if ($this->currentStep < 3) {
            $this->currentStep++;
        }
    }

    #[\Livewire\Attributes\On('previous-step')]
    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    /**
     * Validate what the wizard actually collected.
     *
     * This page renders plain inputs bound to $data rather than Filament
     * components, so there is no form object to ask for state — reaching for
     * one is what threw PropertyNotFoundException on every submit. The rules
     * live here instead, which also means the page validates at all: before
     * this, a blank name reached InventoryItem::create() unchecked.
     *
     * @return array<string, mixed>
     */
    protected function validated(): array
    {
        $data = validator($this->data ?? [], [
            'name'                => ['required', 'string', 'max:255'],
            // Resolved from the model, not spelled as a table name: InventoryItem
            // is a back-compat alias for Product and its table is `products`, so
            // "unique:inventory_items,sku" queries a table that does not exist.
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
            'barcode.unique' => 'That barcode is already on another item — scan it from All Inventory to find it.',
        ])->validate();

        // An untouched input posts "" rather than null, and "" survives
        // `?? $fallback` while behaving as 0 once cast — so a blank Stock Unit
        // Cost silently became $0.00 instead of falling back to the item's
        // cost. For sku and barcode it is worse: both are unique, so the second
        // item saved without either one collides with the first.
        foreach (['sku', 'barcode', 'category', 'unit_cost', 'cost', 'quantity', 'preferred_vendor_id', 'location_id'] as $blankable) {
            if (($data[$blankable] ?? null) === '') {
                $data[$blankable] = null;
            }
        }

        return $data;
    }

    #[\Livewire\Attributes\On('submit-wizard')]
    public function submit(): void
    {
        try {
            $validatedData = $this->validated();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Land the user back on the step that holds the offending field
            // instead of on Review, where the message has nothing to point at.
            $this->currentStep = array_intersect_key(
                $e->validator->failed(),
                array_flip(['location_id', 'quantity', 'cost']),
            ) !== [] ? 2 : 1;

            Notification::make()
                ->title('Check the highlighted fields')
                ->body(collect($e->errors())->flatten()->join(' '))
                ->danger()
                ->send();

            return;
        }

        try {
            // Create the inventory item
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

            $this->createdItem = $item;

            // Add initial stock if provided
            if (($validatedData['location_id'] ?? null) && ($validatedData['quantity'] ?? null)) {
                $location = InventoryLocation::findOrFail($validatedData['location_id']);
                $inventory = app(InventoryService::class);

                $unitCost = (float) ($validatedData['cost'] ?? $item->unit_cost);


                $inventory->addStock(
                    $item,
                    $location,
                    (float) $validatedData['quantity'],
                    'opening',
                    'Initial stock added via quick add wizard',
                    $unitCost,
                );

                $item->load('stock');
            }

            Notification::make()
                ->title('Item added successfully!')
                ->body(($validatedData['quantity'] ?? null) ? 'Item and stock created' : 'Item created (no stock)')
                ->success()
                ->send();

            // Redirect to edit page
            $this->redirect(InventoryItemResource::getUrl('edit', ['record' => $item]));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error creating item')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

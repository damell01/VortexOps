<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Item created successfully';
    }

    protected function getRedirectUrl(): string
    {
        // Default to edit page if record exists
        if ($this->record?->id) {
            return $this->getResource()::getUrl('edit', ['record' => $this->record]);
        }

        // Fall back to list page if record creation failed
        return $this->getResource()::getUrl('index');
    }

    public function mutateFormDataBeforeSave(array $data): array
    {
        // Remove the initial stock fields from the item data
        $initialLocationId = $data['initial_stock_location_id'] ?? null;
        $initialQuantity = $data['initial_stock_quantity'] ?? null;
        $initialCost = $data['initial_stock_cost'] ?? null;

        unset($data['initial_stock_location_id']);
        unset($data['initial_stock_quantity']);
        unset($data['initial_stock_cost']);

        // Store for use after create
        $this->initialStockData = compact('initialLocationId', 'initialQuantity', 'initialCost');

        return $data;
    }

    protected function afterCreate(): void
    {
        if (!isset($this->initialStockData)) {
            return;
        }

        $data = $this->initialStockData;
        $locationId = $data['initialLocationId'];
        $quantity = $data['initialQuantity'];
        $cost = $data['initialCost'];

        // Only add stock if location and quantity are provided
        if (!$locationId || !$quantity) {
            return;
        }

        try {
            $location = InventoryLocation::findOrFail($locationId);
            $inventory = app(InventoryService::class);

            // Use provided cost or fall back to unit cost
            $unitCost = $cost ?? $this->record->unit_cost;

            $inventory->addStock(
                $this->record,
                $location,
                (float) $quantity,
                'opening',
                'Initial stock added on creation',
                (float) $unitCost,
            );

            Notification::make()
                ->title('Initial stock added')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error adding initial stock')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

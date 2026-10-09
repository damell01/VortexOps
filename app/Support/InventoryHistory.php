<?php

namespace App\Support;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryMovement;
use Filament\Actions\Action;

class InventoryHistory
{
    public static function action(): Action
    {
        return Action::make('itemHistory')
            ->label('Item history')->icon('heroicon-o-clock')
            ->slideOver()->modalHeading('Item history')
            ->modalSubmitAction(false)->modalCancelActionLabel('Close')
            ->modalContent(function (array $arguments, $record = null) {
                abort_unless(InventoryItemResource::canAccess(), 403);
                $item = InventoryItemResource::getEloquentQuery()
                    ->findOrFail($record?->id ?? ($arguments['item'] ?? 0));
                $query = InventoryMovement::query()->where('inventory_item_id', $item->id)
                    ->with(['fromLocation', 'toLocation', 'createdByUser']);
                $locations = InventoryVisibility::locationIdsFor(auth()->user());
                if ($locations !== null) {
                    $query->where(fn ($q) => $q->whereIn('from_location_id', $locations)
                        ->orWhereIn('to_location_id', $locations));
                }
                return view('filament.components.inventory-history', [
                    'item' => $item, 'movements' => $query->latest('id')->limit(100)->get(),
                ]);
            });
    }
}

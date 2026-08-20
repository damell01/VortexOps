<?php

namespace App\Filament\Resources\StreamerResource\Pages;

use App\Filament\Resources\StreamerResource;
use App\Filament\Widgets\StreamerEarningsChartWidget;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewStreamer extends ViewRecord
{
    protected static string $resource = StreamerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allocate_to_pool')
                ->label('Allocate to Pool')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->form([
                    Select::make('inventory_item_id')
                        ->label('Item')
                        ->options(fn () => InventoryItem::where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray())
                        ->required()
                        ->searchable(),

                    Select::make('from_location_id')
                        ->label('Source Location')
                        ->options(fn () => InventoryLocation::where('status', 'active')
                            ->whereIn('type', ['main_storage', 'fulfillment', 'other'])
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray())
                        ->required()
                        ->searchable(),

                    TextInput::make('quantity')
                        ->numeric()
                        ->minValue(0.01)
                        ->required(),

                    TextInput::make('reason')
                        ->label('Reason (optional)')
                        ->placeholder('Show prep: Break #47')
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    /** @var \App\Models\Streamer $streamer */
                    $streamer = $this->record;

                    $poolLocation = $streamer->inventoryLocations()
                        ->where('type', 'streamer_inventory')
                        ->where('status', 'active')
                        ->first();

                    if (! $poolLocation) {
                        Notification::make()
                            ->title("No active inventory pool found for {$streamer->name}. Create a Streamer Inventory location first.")
                            ->danger()
                            ->send();
                        return;
                    }

                    $item     = InventoryItem::findOrFail($data['inventory_item_id']);
                    $fromLoc  = InventoryLocation::findOrFail($data['from_location_id']);
                    $qty      = (float) $data['quantity'];

                    $sourceStock = InventoryStock::where('inventory_item_id', $item->id)
                        ->where('inventory_location_id', $fromLoc->id)
                        ->first();

                    if (! $sourceStock || (float) $sourceStock->quantity < $qty) {
                        $available = number_format((float) ($sourceStock?->quantity ?? 0), 2);
                        Notification::make()
                            ->title("Insufficient stock in {$fromLoc->name}. Available: {$available}")
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        // Through the service rather than beside it. This block
                        // used to re-implement transferStock() — the same lock,
                        // the same two writes, the same movement row — which is
                        // how it came to be the one transfer in the app that
                        // does not record the levels either side of itself.
                        // Two copies of an inventory mutation is two places for
                        // it to drift.
                        app(\App\Services\InventoryService::class)->transferStock(
                            item: $item,
                            from: $fromLoc,
                            to: $poolLocation,
                            quantity: $qty,
                            reason: $data['reason'] ?: "Allocated to {$streamer->name} pool",
                        );
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        return;
                    }

                    Notification::make()
                        ->title("Allocated {$qty} × {$item->name} → {$streamer->name}'s pool.")
                        ->success()
                        ->send();
                }),

            EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            StreamerEarningsChartWidget::class,
        ];
    }
}

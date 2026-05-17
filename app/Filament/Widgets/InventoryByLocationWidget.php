<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InventoryLocationResource;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class InventoryByLocationWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    protected static ?string $heading = 'Inventory by Location';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryLocation::query()
                    ->where('status', 'active')
                    ->withCount('stock')
                    ->with('streamer')
            )
            ->columns([
                TextColumn::make('name')
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => InventoryLocation::typeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'main_storage' => 'info',
                        'streamer_inventory' => 'success',
                        'returned' => 'warning',
                        'damaged' => 'danger',
                        'fulfillment' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->placeholder('—'),
                TextColumn::make('stock_count')
                    ->label('SKUs'),
                TextColumn::make('total_units')
                    ->label('Total Units')
                    ->getStateUsing(fn ($record) => number_format(
                        InventoryStock::where('inventory_location_id', $record->id)->sum('quantity'),
                        0
                    )),
                TextColumn::make('stock_value')
                    ->label('Est. Value')
                    ->getStateUsing(fn ($record) => '$' . number_format(
                        InventoryStock::where('inventory_location_id', $record->id)
                            ->join('inventory_items', 'inventory_stock.inventory_item_id', '=', 'inventory_items.id')
                            ->selectRaw('SUM(inventory_stock.quantity * inventory_items.unit_cost) as total')
                            ->value('total') ?? 0,
                        2
                    )),
            ])
            ->recordUrl(fn ($record) => InventoryLocationResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}

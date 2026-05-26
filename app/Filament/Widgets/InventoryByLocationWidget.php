<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InventoryLocationResource;
use App\Models\InventoryLocation;
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
                    ->select([
                        'inventory_locations.id',
                        'inventory_locations.name',
                        'inventory_locations.type',
                        'inventory_locations.streamer_id',
                        'inventory_locations.status',
                    ])
                    ->selectRaw('COUNT(DISTINCT s.inventory_item_id) as sku_count')
                    ->selectRaw('COALESCE(SUM(s.quantity), 0) as total_units')
                    ->selectRaw('COALESCE(SUM(s.quantity * i.unit_cost), 0) as stock_value')
                    ->leftJoin('inventory_stock as s', 's.inventory_location_id', '=', 'inventory_locations.id')
                    ->leftJoin('inventory_items as i', 'i.id', '=', 's.inventory_item_id')
                    ->groupBy([
                        'inventory_locations.id',
                        'inventory_locations.name',
                        'inventory_locations.type',
                        'inventory_locations.streamer_id',
                        'inventory_locations.status',
                    ])
                    ->where('inventory_locations.status', 'active')
                    ->with('streamer:id,name')
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
                TextColumn::make('sku_count')
                    ->label('SKUs')
                    ->getStateUsing(fn ($record) => $record->sku_count ?? 0),
                TextColumn::make('total_units')
                    ->label('Total Units')
                    ->getStateUsing(fn ($record) => number_format($record->total_units ?? 0, 0)),
                TextColumn::make('stock_value')
                    ->label('Est. Value')
                    ->getStateUsing(fn ($record) => '$' . number_format($record->stock_value ?? 0, 2)),
            ])
            ->recordUrl(fn ($record) => InventoryLocationResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}

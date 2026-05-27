<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading = 'Low Stock Alerts';
    protected int | string | array $columnSpan = [
        'md' => 6,
        'xl' => 5,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryItem::query()
                    ->withSum('stock', 'quantity')
                    ->where('is_active', true)
                    ->whereNotNull('reorder_level')
                    ->whereExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('inventory_stock')
                            ->whereColumn('inventory_stock.inventory_item_id', 'inventory_items.id')
                            ->groupBy('inventory_stock.inventory_item_id')
                            ->havingRaw('SUM(quantity) <= inventory_items.reorder_level');
                    })
            )
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('stock_sum_quantity')
                    ->label('Current Qty')
                    ->numeric(decimalPlaces: 0)
                    ->default(0)
                    ->color('danger'),
                TextColumn::make('reorder_level')
                    ->label('Reorder At'),
                TextColumn::make('unit_cost')
                    ->money('USD'),
            ])
            ->recordUrl(fn ($record) => InventoryItemResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}

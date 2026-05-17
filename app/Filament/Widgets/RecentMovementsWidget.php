<?php

namespace App\Filament\Widgets;

use App\Models\InventoryMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentMovementsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Recent Inventory Movements';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryMovement::query()
                    ->with(['item', 'fromLocation', 'toLocation', 'createdByUser'])
                    ->latest()
                    ->limit(15)
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable(),
                TextColumn::make('movement_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => InventoryMovement::movementTypeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'opening' => 'info',
                        'transfer' => 'primary',
                        'adjustment' => 'warning',
                        'sale_deduction' => 'success',
                        'return' => 'gray',
                        'damaged' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 0),
                TextColumn::make('fromLocation.name')
                    ->label('From')
                    ->placeholder('—'),
                TextColumn::make('toLocation.name')
                    ->label('To')
                    ->placeholder('—'),
                TextColumn::make('createdByUser.name')
                    ->label('By')
                    ->placeholder('System'),
            ])
            ->paginated(false);
    }
}

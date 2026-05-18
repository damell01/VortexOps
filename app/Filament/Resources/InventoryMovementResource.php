<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Models\InventoryMovement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-path';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Inventory';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getNavigationLabel(): string
    {
        return 'Movement Log';
    }

    public static function getModelLabel(): string
    {
        return 'Movement';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Movement Log';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['item.name', 'item.sku', 'reason'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->item->name ?? 'Movement') . ' — ' . ($record->movement_type ?? '');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'Date' => $record->created_at?->format('M j, Y'),
            'Qty'  => $record->quantity,
        ]);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable(),
                TextColumn::make('item.sku')
                    ->label('SKU')
                    ->placeholder('—'),
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
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('fromLocation.name')
                    ->label('From')
                    ->placeholder('—'),
                TextColumn::make('toLocation.name')
                    ->label('To')
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('createdByUser.name')
                    ->label('By')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->options(InventoryMovement::movementTypeLabels()),
                SelectFilter::make('inventory_item_id')
                    ->label('Item')
                    ->relationship('item', 'name')
                    ->searchable(),
                SelectFilter::make('from_location_id')
                    ->label('From Location')
                    ->relationship('fromLocation', 'name'),
                SelectFilter::make('to_location_id')
                    ->label('To Location')
                    ->relationship('toLocation', 'name'),
            ])
            ->headerActions([
                TableAction::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn () => route('export.movement-log'))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryMovements::route('/'),
            'view' => Pages\ViewInventoryMovement::route('/{record}'),
        ];
    }
}

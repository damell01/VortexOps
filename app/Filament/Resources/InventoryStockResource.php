<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryStockResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class InventoryStockResource extends Resource
{
    protected static ?string $model = InventoryStock::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Inventory';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationLabel(): string
    {
        return 'Stock Levels';
    }

    public static function getModelLabel(): string
    {
        return 'Stock Level';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Stock Levels';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['item', 'location']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['item.name', 'item.sku', 'location.name'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->item->name ?? '?') . ' @ ' . ($record->location->name ?? '?');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return ['Qty' => number_format((float) $record->quantity, 0)];
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
        return $schema->components([
            Section::make()->schema([
                Grid::make(2)->schema([
                    Select::make('inventory_item_id')
                        ->label('Item')
                        ->relationship('item', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('inventory_location_id')
                        ->label('Location')
                        ->relationship('location', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->minValue(0),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('item.category')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.type')
                    ->label('Location Type')
                    ->formatStateUsing(fn ($state) => InventoryLocation::typeLabels()[$state] ?? $state)
                    ->badge()
                    ->color('info'),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->color(fn ($record) => $record->item?->reorder_level !== null && $record->quantity <= $record->item->reorder_level ? 'danger' : null),
                TextColumn::make('item.unit_cost')
                    ->label('Unit Cost')
                    ->money('USD'),
                TextColumn::make('stock_value')
                    ->label('Stock Value')
                    ->getStateUsing(fn ($record) => $record->quantity * ($record->item?->unit_cost ?? 0))
                    ->money('USD'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('inventory_location_id')
                    ->label('Location')
                    ->relationship('location', 'name'),
                SelectFilter::make('category')
                    ->label('Category')
                    ->options(fn () => Cache::remember('filter:item_categories', 300, fn () => InventoryItem::whereNotNull('category')
                        ->distinct()
                        ->pluck('category', 'category')
                        ->toArray()))
                    ->query(fn ($query, $state) => $state['value']
                        ? $query->whereHas('item', fn ($q) => $q->where('category', $state['value']))
                        : $query),
            ])
            ->headerActions([
                TableAction::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn () => route('export.stock-levels'))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->defaultSort('item.name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryStock::route('/'),
            'edit' => Pages\EditInventoryStock::route('/{record}/edit'),
        ];
    }
}

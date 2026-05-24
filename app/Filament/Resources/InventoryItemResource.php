<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-archive-box';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Inventory';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationLabel(): string
    {
        return 'Items';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withSum('stock', 'quantity');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['sku', 'name', 'category'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'SKU'      => $record->sku,
            'Category' => $record->category,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Item Details')->schema([
                Grid::make(2)->schema([
                    TextInput::make('sku')
                        ->label('SKU')
                        ->unique(ignoreRecord: true)
                        ->maxLength(100),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('category')
                        ->maxLength(100),
                    TextInput::make('unit_cost')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->default(0),
                    TextInput::make('reorder_level')
                        ->numeric()
                        ->minValue(0)
                        ->label('Reorder Level (units)'),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
                Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('unit_cost')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('stock_sum_quantity')
                    ->label('Total Qty')
                    ->numeric(decimalPlaces: 0)
                    ->default(0)
                    ->sortable(),
                TextColumn::make('reorder_level')
                    ->label('Reorder At')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(fn () => InventoryItem::whereNotNull('category')
                        ->distinct()
                        ->pluck('category', 'category')
                        ->toArray()),
                Filter::make('low_stock')
                    ->label('Low Stock Only')
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('reorder_level')
                        ->whereExists(function ($q) {
                            $q->selectRaw('1')
                                ->from('inventory_stock')
                                ->whereColumn('inventory_stock.inventory_item_id', 'inventory_items.id')
                                ->groupBy('inventory_stock.inventory_item_id')
                                ->havingRaw('SUM(quantity) <= inventory_items.reorder_level');
                        })
                    ),
                Filter::make('is_active')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->where('is_active', true))
                    ->default(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    Action::make('add_stock')
                        ->label('Add Stock')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->form([
                            Select::make('location_id')
                                ->label('Location')
                                ->options(fn () => InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->label('Quantity to Add'),
                            Select::make('movement_type')
                                ->options(['opening' => 'Opening Stock', 'adjustment' => 'Adjustment', 'return' => 'Return'])
                                ->default('opening')
                                ->required(),
                            Textarea::make('reason')->rows(2),
                        ])
                        ->action(function (InventoryItem $record, array $data): void {
                            $location = InventoryLocation::findOrFail($data['location_id']);
                            app(InventoryService::class)->addStock(
                                $record,
                                $location,
                                (float) $data['quantity'],
                                $data['movement_type'],
                                $data['reason'] ?? null
                            );
                            Notification::make()->title('Stock added successfully')->success()->send();
                        }),

                    Action::make('transfer_stock')
                        ->label('Transfer Stock')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('info')
                        ->form([
                            Select::make('from_location_id')
                                ->label('From Location')
                                ->options(fn () => InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            Select::make('to_location_id')
                                ->label('To Location')
                                ->options(fn () => InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->label('Quantity to Transfer'),
                            Textarea::make('reason')->rows(2),
                        ])
                        ->action(function (InventoryItem $record, array $data): void {
                            $from = InventoryLocation::findOrFail($data['from_location_id']);
                            $to = InventoryLocation::findOrFail($data['to_location_id']);
                            app(InventoryService::class)->transferStock($record, $from, $to, (float) $data['quantity'], $data['reason'] ?? null);
                            Notification::make()->title('Stock transferred successfully')->success()->send();
                        }),

                    Action::make('adjust_inventory')
                        ->label('Adjust Inventory')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->form([
                            Select::make('location_id')
                                ->label('Location')
                                ->options(fn () => InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            TextInput::make('new_quantity')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->label('New Quantity (set to exact amount)'),
                            Textarea::make('reason')
                                ->rows(2)
                                ->required()
                                ->label('Reason for adjustment'),
                        ])
                        ->action(function (InventoryItem $record, array $data): void {
                            $location = InventoryLocation::findOrFail($data['location_id']);
                            app(InventoryService::class)->adjustStock($record, $location, (float) $data['new_quantity'], $data['reason']);
                            Notification::make()->title('Inventory adjusted')->success()->send();
                        }),

                    Action::make('mark_damaged')
                        ->label('Mark Damaged')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->form([
                            Select::make('from_location_id')
                                ->label('From Location')
                                ->options(fn () => InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            Select::make('damaged_location_id')
                                ->label('Damaged Inventory Location')
                                ->options(fn () => InventoryLocation::where('type', 'damaged')->where('status', 'active')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->minValue(0.01),
                            Textarea::make('reason')->rows(2),
                        ])
                        ->action(function (InventoryItem $record, array $data): void {
                            $from = InventoryLocation::findOrFail($data['from_location_id']);
                            $damaged = InventoryLocation::findOrFail($data['damaged_location_id']);
                            app(InventoryService::class)->markDamaged($record, $from, $damaged, (float) $data['quantity'], $data['reason'] ?? null);
                            Notification::make()->title('Items marked as damaged')->warning()->send();
                        }),

                    Action::make('move_to_returns')
                        ->label('Move to Returns')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('gray')
                        ->form([
                            Select::make('from_location_id')
                                ->label('From Location')
                                ->options(fn () => InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            Select::make('returns_location_id')
                                ->label('Returns Location')
                                ->options(fn () => InventoryLocation::where('type', 'returned')->where('status', 'active')->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->minValue(0.01),
                            Textarea::make('reason')->rows(2),
                        ])
                        ->action(function (InventoryItem $record, array $data): void {
                            $from = InventoryLocation::findOrFail($data['from_location_id']);
                            $returns = InventoryLocation::findOrFail($data['returns_location_id']);
                            app(InventoryService::class)->moveToReturns($record, $from, $returns, (float) $data['quantity'], $data['reason'] ?? null);
                            Notification::make()->title('Items moved to returns')->success()->send();
                        }),
                ]),
            ])
            ->headerActions([
                TableAction::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn () => route('export.inventory-items'))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->deferLoading()
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'view' => Pages\ViewInventoryItem::route('/{record}'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }
}

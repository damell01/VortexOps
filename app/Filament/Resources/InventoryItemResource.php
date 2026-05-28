<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Actions\Action as TableAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class InventoryItemResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $model = InventoryItem::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-archive-box';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
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
        return ['sku', 'barcode', 'name', 'category'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'SKU' => $record->sku,
            'Barcode' => $record->barcode,
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
                    TextInput::make('barcode')
                        ->label('Barcode / Scan Code')
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('category')
                        ->maxLength(100),
                    TextInput::make('unit_cost')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->default(0)
                        ->label('Base Unit Cost'),
                    TextInput::make('seller_unit_cost')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->label('Seller Cost / Unit'),
                    TextInput::make('shipping_unit_cost')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->label('Shipping / Unit'),
                    TextInput::make('other_unit_fees')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->label('Other Fees / Unit'),
                    TextInput::make('average_unit_cost')
                        ->numeric()
                        ->prefix('$')
                        ->label('Average Unit Cost')
                        ->helperText('Optional override when the average paid cost varies across invoices or lots.'),
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
                Textarea::make('cost_notes')
                    ->rows(2)
                    ->label('Cost Notes / Extra Fees')
                    ->helperText('Use this for custom fees or tracked cost details until exact fee categories are finalized.')
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
                    ->placeholder('-')
                    ->description(fn (InventoryItem $record): ?string => $record->barcode ?: null),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (InventoryItem $record): ?string => filled($record->description) ? str($record->description)->limit(48)->toString() : null),
                TextColumn::make('category')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),
                TextColumn::make('unit_cost')
                    ->label('Base Cost')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('landed_unit_cost')
                    ->label('Landed Cost')
                    ->money('USD')
                    ->state(fn (InventoryItem $record): float => $record->landed_unit_cost)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('(seller_unit_cost + shipping_unit_cost + other_unit_fees) ' . $direction)),
                TextColumn::make('average_unit_cost')
                    ->label('Avg Cost')
                    ->money('USD')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('stock_sum_quantity')
                    ->label('On Hand')
                    ->numeric(decimalPlaces: 0)
                    ->default(0)
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('reorder_level')
                    ->label('Reorder At')
                    ->placeholder('-')
                    ->description(fn (InventoryItem $record): ?string => $record->reorder_level !== null && (int) ($record->stock_sum_quantity ?? 0) <= (int) $record->reorder_level ? 'Needs restock' : null),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('By Category')
                    ->options(fn () => Cache::remember('filter:item_categories', 300, fn () => InventoryItem::whereNotNull('category')
                        ->distinct()
                        ->pluck('category', 'category')
                        ->toArray())),
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
            ->recordUrl(fn (InventoryItem $record): string => static::getUrl('view', ['record' => $record]))
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
                                ->options(fn () => InventoryLocation::activeOptions())
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
                                ->options(fn () => InventoryLocation::activeOptions())
                                ->required()
                                ->searchable(),
                            Select::make('to_location_id')
                                ->label('To Location')
                                ->options(fn () => InventoryLocation::activeOptions())
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
                                ->options(fn () => InventoryLocation::activeOptions())
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
                                ->options(fn () => InventoryLocation::activeOptions())
                                ->required()
                                ->searchable(),
                            Select::make('damaged_location_id')
                                ->label('Damaged Inventory Location')
                                ->options(fn () => InventoryLocation::activeOptionsByType('damaged'))
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
                                ->options(fn () => InventoryLocation::activeOptions())
                                ->required()
                                ->searchable(),
                            Select::make('returns_location_id')
                                ->label('Returns Location')
                                ->options(fn () => InventoryLocation::activeOptionsByType('returned'))
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
            ->persistFiltersInSession()
            ->stackedOnMobile()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
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

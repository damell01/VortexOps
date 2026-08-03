<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Vendor;
use App\Services\InventoryService;
use App\Support\AdminModules;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action as TableAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class InventoryItemResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'inventory';

    protected static ?string $model = InventoryItem::class;

    // Global search does substring LIKE, which can't use an index; cap results
    // low so ⌘K stays cheap on a very large catalogue.
    protected static int $globalSearchResultsLimit = 15;

    /**
     * InventoryItem (Product) is soft-deletable, so this never destroys
     * stock/movement history the way a hard delete would — but still block
     * while it holds real stock so it can't silently vanish from pickers
     * while units are still on hand.
     */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (auth()->user()?->isAdmin() ?? false)
            && ! $record->stock()->where('quantity', '>', 0)->exists();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

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
        return 'All Inventory';
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
            Section::make('Item Identification')
                ->description('Name, SKU, and barcode for tracking and scanning')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Item Name')
                            ->placeholder('e.g., 2024 Topps Chrome Box')
                            ->columnSpan(2),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->columnSpan(1)
                            ->helperText('Inactive items won\'t appear in dropdowns'),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->default(fn () => 'VB' . date('ymd') . strtoupper(\Illuminate\Support\Str::random(4)))
                            ->helperText('Auto-generated — edit to customize')
                            ->columnSpan(2)
                            ->suffixAction(
                                \Filament\Actions\Action::make('regenerate_sku')
                                    ->icon('heroicon-o-arrow-path')
                                    ->tooltip('Generate new SKU')
                                    ->action(function (\Filament\Forms\Set $set) {
                                        $set('sku', 'VB' . date('ymd') . strtoupper(\Illuminate\Support\Str::random(4)));
                                    })
                            ),
                        TextInput::make('barcode')
                            ->label('Barcode/UPC')
                            ->unique(ignoreRecord: true)
                            ->maxLength(100)
                            ->helperText('Bluetooth scanner input. Camera scan not yet available.')
                            ->columnSpan(1),
                    ]),
                ]),

            Section::make('Classification & Sourcing')
                ->description('Organize and track inventory by category and vendor')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('category')
                            ->options(fn () => Cache::remember('filter:item_categories', 300, fn () => InventoryItem::whereNotNull('category')
                                ->distinct()->orderBy('category')->pluck('category', 'category')->toArray()))
                            ->getOptionLabelUsing(fn ($value) => $value)
                            ->searchable()
                            ->native(false)
                            ->placeholder('Select or create category...')
                            ->createOptionForm([
                                TextInput::make('category')
                                    ->label('New category')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->createOptionUsing(fn (array $data) => $data['category'])
                            ->helperText('Group items by type (e.g., Sports Cards, Autographs)'),
                        Select::make('preferred_vendor_id')
                            ->label('Preferred Vendor')
                            ->options(fn () => Vendor::activeOptions())
                            ->searchable()
                            ->nullable()
                            ->placeholder('No preferred vendor'),
                    ]),
                ]),

            Section::make('Pricing & Inventory Levels')
                ->description('Set costs and reorder points')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('unit_cost')
                            ->label('List Unit Cost ($)')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->default(0)
                            ->step(0.01)
                            ->helperText('Fallback cost when no receipts exist'),
                        TextInput::make('average_cost')
                            ->label('Avg Cost ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->step(0.0001)
                            ->helperText('Auto-calculated from receiving history'),
                        TextInput::make('reorder_level')
                            ->numeric()
                            ->minValue(0)
                            ->label('Reorder Level (units)')
                            ->placeholder('0')
                            ->helperText('Alert when stock drops below this'),
                    ]),
                ]),

            Section::make('Notes & Description')
                ->description('Additional details about this item')
                ->columnSpanFull()
                ->schema([
                    Textarea::make('description')
                        ->rows(3)
                        ->placeholder('Brand, set, year, condition, or other details...')
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->rows(2)
                        ->placeholder('Internal notes for your team...')
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
                    ->sortable()
                    ->copyable()
                    ->placeholder('—')
                    ->weight('semibold'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn ($record) => $record->description),
                TextColumn::make('category')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('stock_sum_quantity')
                    ->label('Stock')
                    ->numeric(decimalPlaces: 0)
                    ->default(0)
                    ->sortable()
                    ->summarize(Sum::make()->label('Total'))
                    ->color(fn ($record) => match (true) {
                        (int) ($record->stock_sum_quantity ?? 0) <= 0 => 'danger',
                        isset($record->reorder_level) && (int) ($record->stock_sum_quantity ?? 0) <= (int) $record->reorder_level => 'warning',
                        default => 'success'
                    })
                    ->weight(fn ($record) => (int) ($record->stock_sum_quantity ?? 0) <= 0 ? 'bold' : 'normal')
                    ->icon(fn ($record) => match (true) {
                        (int) ($record->stock_sum_quantity ?? 0) <= 0 => 'heroicon-o-exclamation-triangle',
                        isset($record->reorder_level) && (int) ($record->stock_sum_quantity ?? 0) <= (int) $record->reorder_level => 'heroicon-o-exclamation-circle',
                        default => 'heroicon-o-check-circle'
                    }),
                TextColumn::make('reorder_level')
                    ->label('Reorder At')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('average_cost')
                    ->label('Avg Cost')
                    ->money('USD')
                    ->sortable()
                    ->toggleable()
                    ->description(fn ($record) => $record->total_units_received > 0
                        ? '(' . number_format((float) $record->total_units_received, 0) . ' units)'
                        : null),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->toggleable(),
                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('No inventory items yet')
            ->emptyStateDescription('Add the products you stock and break. You can also create items on the fly while receiving pallets.')
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()->label('Add an item'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(fn () => Cache::remember('filter:item_categories', 300, fn () => InventoryItem::whereNotNull('category')
                        ->distinct()
                        ->pluck('category', 'category')
                        ->toArray()))
                    ->multiple(),
                Filter::make('low_stock')
                    ->label('Low Stock Only')
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('reorder_level')
                        ->whereExists(function ($q) {
                            $q->selectRaw('1')
                                ->from('inventory_stock')
                                ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                                ->groupBy('inventory_stock.inventory_item_id')
                                ->havingRaw('SUM(quantity) <= products.reorder_level');
                        })
                    ),
                Filter::make('is_active')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->where('is_active', true))
                    ->default(),
                QueryBuilder::make()
                    ->label('Advanced Filters')
                    ->constraintPickerColumns(2)
                    ->constraints([
                        TextConstraint::make('name')->label('Item Name'),
                        TextConstraint::make('sku')->label('SKU'),
                        TextConstraint::make('barcode')->label('Barcode'),
                        SelectConstraint::make('category')
                            ->options(fn () => Cache::remember('filter:item_categories', 300, fn () => InventoryItem::whereNotNull('category')
                                ->distinct()->pluck('category', 'category')->toArray()))
                            ->multiple(),
                        NumberConstraint::make('average_cost')->label('Avg Cost ($)'),
                        NumberConstraint::make('reorder_level')->label('Reorder Level'),
                        BooleanConstraint::make('is_active')->label('Active'),
                    ]),
            ])
            ->actions([
                ViewAction::make()->label('View'),
                EditAction::make()->label('Edit'),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (InventoryItem $record) => static::canDelete($record))
                    ->tooltip(fn (InventoryItem $record) => static::canDelete($record) ? 'Delete item' : 'Still holds stock — move or zero it out first.'),
                ActionGroup::make([
                    Action::make('add_stock')
                        ->label('Add Stock')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->description('Add units to a location (opening stock, restock, or return)')
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
                                ->options(['opening' => 'Opening Stock / Restock', 'adjustment' => 'Adjustment', 'return' => 'Return'])
                                ->default('opening')
                                ->live()
                                ->required(),
                            TextInput::make('unit_cost')
                                ->label('Unit Cost ($)')
                                ->numeric()
                                ->minValue(0)
                                ->visible(fn (Get $get) => $get('movement_type') === 'opening')
                                ->helperText('Blends into this item\'s weighted average cost. Leave blank to add stock without changing the average.'),
                            Textarea::make('reason')->rows(2),
                        ])
                        ->action(function (InventoryItem $record, array $data): void {
                            $location = InventoryLocation::findOrFail($data['location_id']);
                            app(InventoryService::class)->addStock(
                                $record,
                                $location,
                                (float) $data['quantity'],
                                $data['movement_type'],
                                $data['reason'] ?? null,
                                isset($data['unit_cost']) && $data['unit_cost'] !== null && $data['unit_cost'] !== ''
                                    ? (float) $data['unit_cost']
                                    : null,
                            );
                            Notification::make()->title('Stock added successfully')->success()->send();
                        }),

                    Action::make('transfer_stock')
                        ->label('Transfer Stock')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('info')
                        ->description('Move units between locations')
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
                        ->description('Set exact quantity for a location (count discrepancy)')
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
                        ->description('Move damaged units to damaged inventory location')
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
                        ->description('Move units to returns/RMA location')
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
                    ExportBulkAction::make(),
                    DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $deletable = $records->filter(fn (InventoryItem $record) => static::canDelete($record));
                            $blocked   = $records->count() - $deletable->count();

                            $deletable->each->delete();

                            if ($blocked > 0) {
                                Notification::make()
                                    ->title($deletable->count() . ' item(s) deleted')
                                    ->body("{$blocked} skipped — still hold stock.")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()->title($deletable->count() . ' item(s) deleted')->success()->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\InventoryItemResource\RelationManagers\BarcodesRelationManager::class,
        ];
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

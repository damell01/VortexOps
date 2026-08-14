<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryItemContent;
use App\Models\InventoryLocation;
use App\Models\Vendor;
use App\Services\InventoryService;
use App\Support\AdminModules;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use App\Support\NavVisibility;

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

    public static function canAccess(): bool
    {
        $user = auth()->user();

        // Allow streamers to access for creating items even if module is disabled
        if ($user?->isStreamer() && !$user->isAdmin() && !$user->isOwner()) {
            return true;
        }

        // Default module access check for admins/owners
        return parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        $user = auth()->user();

        // Show navigation for streamers even if inventory module is disabled
        if ($user?->isStreamer() && !$user->isAdmin() && !$user->isOwner()) {
            return true;
        }

        // Use default registration for admins/owners (respects module gating)
        return parent::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false) || ($user?->isStreamer() ?? false);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        // Admins and owners can always edit
        if ($user?->isAdmin() || $user?->isOwner()) {
            return true;
        }

        // Streamers can only edit items in their assigned inventory locations
        if ($user?->isStreamer()) {
            $streamer = $user->streamer;
            if (!$streamer) {
                return false;
            }

            $streamerLocationIds = $streamer->inventoryLocations()->pluck('id');
            return $record->stock()
                ->whereIn('inventory_location_id', $streamerLocationIds)
                ->exists();
        }

        return false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-archive-box';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        // All users see it under the Inventory group
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationLabel(): string
    {
        return 'All Inventory';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withSum('stock', 'quantity');

        // Streamers only see their own inventory locations
        $user = auth()->user();
        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            $streamer = $user->streamer;
            if ($streamer) {
                $locationIds = $streamer->inventoryLocations()->pluck('id');
                return $query->whereHas('stock', fn ($q) =>
                    $q->whereIn('inventory_location_id', $locationIds)
                )->distinct();
            }
        }

        return $query;
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
        // Keys drive the layout in the global-search override: 'sku' is the
        // muted second line, 'status' becomes a pill, 'figure' is pinned
        // right. Anything else renders as a plain label/value pair.
        $onHand = (int) ($record->stock_sum_quantity ?? $record->stock()->sum('quantity'));

        return array_filter([
            'sku'    => $record->sku,
            'status' => match (static::stockStatus($record)) {
                'out'   => 'Out of Stock',
                'low'   => 'Low Stock',
                default => 'In Stock',
            },
            'figure' => number_format($onHand) . ' units',
        ]);
    }

    /** Global search hits need the stock sum the details above read. */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->withSum('stock', 'quantity');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Item Identification')
                ->icon('heroicon-o-identification')
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
                            ->helperText('Scan with camera, Bluetooth scanner, or type manually')
                            ->columnSpan(1)
                            ->suffixAction(
                                \Filament\Actions\Action::make('scan_barcode')
                                    ->icon('heroicon-o-video-camera')
                                    ->tooltip('Open camera scanner')
                                    ->action(fn () => null) // Handled by inline JS
                                    ->extraAttributes([
                                        'onclick' => "window.dispatchEvent(new Event('open-camera-scanner'))",
                                        'type' => 'button',
                                    ])
                            ),
                    ]),
                ]),

            Section::make('Container / Case Settings')
                ->icon('heroicon-o-cube')
                ->description('Define if this item is a container that holds other inventory items.')
                ->columnSpanFull()
                ->schema([
                    // Two labelled choices rather than a toggle: "container or
                    // not" is a decision about what the thing is, and a bare
                    // switch gave no hint which way to go.
                    Radio::make('is_container')
                        ->label('')
                        ->options([
                            1 => 'This is a container (case, box, pack)',
                            0 => 'This is a single item',
                        ])
                        ->descriptions([
                            1 => 'Holds individual items (SKUs) inside it. Recommended for cases, boxes, or packs.',
                            0 => 'Sold and tracked on its own.',
                        ])
                        ->default(0)
                        ->inline()
                        ->extraAttributes(['class' => 'vx-choice-cards'])
                        ->live(),
                    Repeater::make('childContents')
                        ->relationship('childContents', modifyQueryUsing: fn ($query) => $query->with('childItem'))
                        ->label('Items Inside This Container')
                        ->visible(fn (Get $get) => $get('is_container'))
                        ->addActionLabel('Add Item Inside')
                        ->columnSpanFull()
                        ->schema([
                            Grid::make(12)->schema([
                                Select::make('child_inventory_item_id')
                                    ->label('Item')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search) => InventoryItem::where('is_active', true)
                                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%"))
                                        ->whereNot('id', fn ($q) => $q->select('id')->from('products')->where('is_container', true)->limit(1))
                                        ->orderBy('name')
                                        ->limit(30)
                                        ->pluck('name', 'id')
                                        ->toArray())
                                    ->getOptionLabelUsing(fn ($value) => InventoryItem::find($value)?->name)
                                    ->required()
                                    ->columnSpan(6)
                                    ->createOptionForm([
                                        TextInput::make('name')->label('Item Name')->required(),
                                        TextInput::make('sku')->label('SKU')->required(),
                                        TextInput::make('barcode')->label('Barcode (optional)'),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        return InventoryItem::create(array_merge($data, ['is_active' => true, 'is_container' => false]))->getKey();
                                    }),
                                TextInput::make('quantity_per_parent')
                                    ->label('Qty Inside')
                                    ->numeric()
                                    ->step(1)
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->columnSpan(3),
                                TextInput::make('unit_type')
                                    ->label('Unit Type')
                                    ->placeholder('e.g., box, pack, bundle')
                                    ->helperText('Describes what you\'re counting')
                                    ->columnSpan(3),
                            ]),
                        ]),
                ]),

            Section::make('Classification & Sourcing')
                ->icon('heroicon-o-rectangle-stack')
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
                ->icon('heroicon-o-currency-dollar')
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
                ->icon('heroicon-o-document-text')
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

            Section::make('Initial Stock (Optional)')
                ->icon('heroicon-o-inbox-arrow-down')
                ->description('Add stock when creating this item')
                ->columnSpanFull()
                ->visible(fn (Get $get) => !$get('id'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('initial_stock_location_id')
                            ->label('Stock Location')
                            ->options(fn () => InventoryLocation::activeOptions())
                            ->searchable()
                            ->dehydrated(false)
                            ->placeholder('Select location to add stock'),
                        TextInput::make('initial_stock_quantity')
                            ->label('Initial Quantity')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->dehydrated(false)
                            ->placeholder('0'),
                        TextInput::make('initial_stock_cost')
                            ->label('Stock Unit Cost ($)')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->dehydrated(false)
                            ->placeholder('Leave blank to use List Unit Cost'),
                    ]),
                ]),

            Section::make('Stock by Location')
                ->icon('heroicon-o-map-pin')
                ->description('Current inventory levels by location')
                ->columnSpanFull()
                ->visible(fn (Get $get) => !!$get('id'))
                ->schema([
                    Repeater::make('stock')
                        ->relationship('stock')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('location.name')
                                    ->label('Location')
                                    ->disabled()
                                    ->columnSpan(2)
                                    ->dehydrated(false),
                                TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->step(0.01)
                                    ->columnSpan(1),
                            ]),
                        ])
                        ->columnSpanFull()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ]),

        ]);
    }

    /**
     * Stock health for a row: 'out', 'low' or 'ok'.
     *
     * An item with no reorder level set can only be out or in stock — there's
     * no threshold to be "low" against.
     */
    public static function stockStatus(InventoryItem $record): string
    {
        $onHand = (float) ($record->stock_sum_quantity ?? 0);

        if ($onHand <= 0) {
            return 'out';
        }

        return $record->reorder_level !== null && $onHand <= (float) $record->reorder_level
            ? 'low'
            : 'ok';
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
                    ->weight('semibold')
                    // Null rather than an em dash: Filament skips the second
                    // line entirely, so rows without a barcode stay one line
                    // tall instead of carrying an empty placeholder.
                    ->description(fn ($record) => filled($record->barcode) ? $record->barcode : null),
                TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    // Containers get a marker so it's obvious which rows have
                    // something to open; the Contents action does the rest.
                    ->icon(fn ($record) => $record->is_container ? 'heroicon-m-archive-box' : null)
                    ->iconPosition(\Filament\Support\Enums\IconPosition::Before)
                    ->iconColor('primary')
                    ->description(fn ($record) => filled($record->description)
                        ? \Illuminate\Support\Str::limit($record->description, 70)
                        : null),
                // Stock health, not the active flag — this is the status the
                // tiles above the table count, so the two agree at a glance.
                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn ($record) => static::stockStatus($record))
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'out'   => 'Out of Stock',
                        'low'   => 'Low Stock',
                        default => 'In Stock',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'out'   => 'danger',
                        'low'   => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('stock_sum_quantity')
                    ->label('Qty on Hand')
                    ->numeric(decimalPlaces: 0)
                    ->default(0)
                    ->sortable()
                    ->weight('semibold')
                    // The tiles above the table already carry the totals, so
                    // the summary row is redundant noise under every page.
                    ->color(fn ($record) => match (static::stockStatus($record)) {
                        'out'   => 'danger',
                        'low'   => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('category')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reorder_level')
                    // hiddenFrom('md') hid the header but not the data cells,
                    // leaving an unlabeled column on desktop, so this is simply
                    // a visible column: the card pairs it with Stock on phones.
                    ->label('Reorder Level')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('average_cost')
                    ->label('Avg Cost')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn ($record) =>
                        ((int) ($record->stock_sum_quantity ?? 0)) > 0
                            ? number_format((int) ($record->stock_sum_quantity ?? 0)) . ' units • $' . number_format(((int) ($record->stock_sum_quantity ?? 0)) * ((float) ($record->average_cost ?? 0)), 2)
                            : '(' . number_format((float) $record->total_units_received, 0) . ' units received)'
                    ),
                TextColumn::make('inventory_value')
                    ->label('Inventory Value')
                    ->getStateUsing(fn ($record) => ((int) ($record->stock_sum_quantity ?? 0)) * ((float) ($record->average_cost ?? 0)))
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    // Card footer on phones; a normal sortable column on desktop.
                    ->label('Updated')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->striped()
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('No inventory items yet')
            ->emptyStateDescription('Add the products you stock and break. You can also create items on the fly while receiving pallets.')
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()
                    ->label('+ Add Inventory')
                    ->color('success')
                    ->visible(function () {
                        $user = auth()->user();
                        return ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false) || ($user?->isStreamer() ?? false);
                    }),
            ])
            // AboveContent turned the Advanced Filters query builder into a
            // full-height panel that pushed the table below the fold. The
            // dropdown keeps a compact trigger with an active-filter count.
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Dropdown)
            ->filtersTriggerAction(fn (TableAction $action) => $action
                ->label('Filters')
                ->icon('heroicon-m-funnel')
                ->button()
                ->color('gray'))
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
                // Only rendered for containers, so it sits alongside view/edit
                // as a third icon rather than adding a column to every row.
                TableAction::make('contents')
                    ->label('Contents')
                    ->icon('heroicon-o-archive-box')
                    ->color('primary')
                    ->size('sm')
                    ->iconButton()
                    ->tooltip('See what is inside')
                    ->visible(fn ($record) => (bool) $record->is_container)
                    ->modalHeading(fn ($record) => 'Inside ' . $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('2xl')
                    ->modalContent(fn ($record) => view(
                        'filament.modals.container-contents',
                        // Eager-loaded here: lazy loading is off outside
                        // production, so the view would fatal otherwise.
                        ['record' => $record->load('childContents.childItem.stock')],
                    )),
                ViewAction::make()
                    ->size('sm')
                    ->iconButton(),
                EditAction::make()
                    ->size('sm')
                    ->iconButton(),
                // Everything past view/edit lives behind the overflow menu, so
                // a row is three controls wide instead of five.
                ActionGroup::make([
                    Action::make('add_stock')
                        ->label('Add Stock')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->form([
                            Grid::make(2)->schema([
                                Select::make('location_id')
                                    ->label('Location')
                                    ->options(fn () => InventoryLocation::activeOptions())
                                    ->required()
                                    ->searchable()
                                    ->columnSpan(1),
                                Select::make('vendor_id')
                                    ->label('Vendor')
                                    ->options(fn () => Vendor::activeOptions())
                                    ->searchable()
                                    ->columnSpan(1)
                                    ->helperText('Track which vendor this stock came from'),
                            ]),
                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->label('Quantity to Add'),
                            TextInput::make('unit_cost')
                                ->label('Unit Cost ($)')
                                ->numeric()
                                ->minValue(0)
                                ->helperText('Blends into this item\'s weighted average cost. Leave blank to add stock without changing the average.'),
                            Textarea::make('reason')->rows(2)->placeholder('e.g., Restock from Vendor, damaged replacement, etc.'),
                        ])
                        ->action(function (InventoryItem $record, array $data): void {
                            $location = InventoryLocation::findOrFail($data['location_id']);
                            $reason = $data['reason'] ?? '';
                            if (isset($data['vendor_id'])) {
                                $vendor = Vendor::find($data['vendor_id']);
                                $reason = ($reason ? $reason . ' — ' : '') . 'From ' . ($vendor?->name ?? 'Unknown Vendor');
                            }
                            app(InventoryService::class)->addStock(
                                $record,
                                $location,
                                (float) $data['quantity'],
                                'opening',
                                $reason ?: null,
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
                    Action::make('scan_barcode')
                        ->label('Scan Barcode')
                        ->icon('heroicon-o-qr-code')
                        ->color('info')
                        ->action(function (InventoryItem $record): void {
                            Notification::make()
                                ->title('📱 Scan via item')
                                ->body('Edit the item to use the barcode scanner.')
                                ->info()
                                ->send();
                        }),
                    DeleteAction::make(),
                ]),
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
            'quick-add' => Pages\QuickAddInventoryItem::route('/quick-add'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'view' => Pages\ViewInventoryItem::route('/{record}'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }
}

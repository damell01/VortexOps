<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\InventoryItemResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryItemContent;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Vendor;
use App\Services\InventoryService;
use App\Support\AdminModules;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();

        // Allow streamers to access for creating items even if module is disabled
        if ($user?->isStreamer() && !$user->isAdmin() && !$user->isOwner()) {
            return true;
        }

        // Default module access check for admins/owners
        return parent::canAccess();
    }

    // No shouldRegisterNavigation() override. It ended in
    // parent::shouldRegisterNavigation(), and `parent::` is Filament's base
    // resource, not the trait — so it skipped the trait's rule and registered
    // the link without asking whether the page would open, which is how
    // fulfillment users got a link here that 403'd. Its other branch showed
    // streamers a link even with the inventory module off, which canAccess()
    // refuses. The trait derives the link from canAccess() for everyone.

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false) || ($user?->isStreamer() ?? false);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // "Can Edit" on Roles & Permissions, which used to be a checkbox
        // nothing read.
        if (\App\Support\RoleAccess::allowsEditing(static::class)) {
            return true;
        }

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

        // This used to limit streamers to their own shelves, which meant they
        // could not see that a case existed anywhere else — and so could not
        // ask for one. What they can see is now a decision made in Settings
        // (Inventory → what streamers can see), with their own location always
        // included because it is theirs.
        $visible = \App\Support\InventoryVisibility::locationIdsFor(auth()->user());

        if ($visible !== null) {
            return $query
                ->whereHas('stock', fn ($q) => $q->whereIn('inventory_location_id', $visible))
                ->distinct();
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
        // Lowercase keys are slots in the global-search override, not labels:
        // subtitle / status / tone / figure. See that view for the contract.
        $onHand = (int) ($record->stock_sum_quantity ?? $record->stock()->sum('quantity'));
        $state  = static::stockStatus($record);

        return array_filter([
            'subtitle' => filled($record->sku) ? 'SKU: ' . $record->sku : null,
            'status'   => match ($state) {
                'out'   => 'Out of Stock',
                'low'   => 'Low Stock',
                default => 'In Stock',
            },
            'tone' => match ($state) {
                'out'   => 'danger',
                'low'   => 'warning',
                default => 'success',
            },
            'figure' => number_format($onHand) . ' ' . \Illuminate\Support\Str::plural('unit', $onHand),
        ]);
    }

    /** Global search hits need the stock sum the details above read. */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->withSum('stock', 'quantity');
    }

    public static function form(Schema $schema): Schema
    {
        // Flat on edit. Create groups the same sections into wizard steps
        // via CreateInventoryItem::getSteps(), so there is one definition
        // of every field rather than two that drift apart.
        return $schema->components(static::formSections());
    }

    /** @return array<int, Section> */
    public static function formSections(): array
    {
        return [
            static::itemIdentificationSection(),
            static::containerSettingsSection(),
            static::classificationSection(),
            static::pricingSection(),
            static::notesSection(),
            static::initialStockSection(),
            static::stockByLocationSection(),
        ];
    }

    public static function itemIdentificationSection(): Section
    {
        return Section::make('Item Identification')
            ->icon('heroicon-o-identification')
            ->description('Name, SKU, and barcode for tracking and scanning')
            ->columnSpanFull()
            ->schema([
                // A photo settles "is this the right box" faster than any of
                // the text below it, and card product names are near-identical
                // by design. Disk named explicitly: the app default is `local`,
                // which the public symlink these are served through cannot see.
                \Filament\Forms\Components\FileUpload::make('image_path')
                    ->label('Photo')
                    // Deliberately not ->image() or ->imageEditor(). Both make
                    // Laravel re-read the Livewire temp file when the form is
                    // saved — validateImage() stats it for dimensions — and by
                    // then the upload has been consumed, which threw
                    // UnableToRetrieveMetadata and blocked the save entirely.
                    // acceptedFileTypes already restricts this to images, and
                    // this mirrors the pallet attachment upload that works.
                    ->disk(\App\Models\Product::IMAGE_DISK)
                    ->directory('products')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    ->helperText('Optional. Up to 5MB — or use Take Photo to capture one now.')
                    // Shrunk once it is safely stored. A phone writes several
                    // megabytes at 4000px and nothing here renders a product
                    // above a few hundred, so the rest is paid for on every
                    // backup and every page load for pixels no one sees.
                    ->saveUploadedFileUsing(function ($file, $component) {
                        $path = $file->store($component->getDirectory(), [
                            'disk'       => $component->getDiskName(),
                            'visibility' => 'public',
                        ]);

                        app(\App\Services\ImageCompressor::class)
                            ->compress($component->getDiskName(), $path);

                        return $path;
                    })
                    ->columnSpanFull(),
                \Filament\Schemas\Components\View::make('filament.components.photo-capture-button')
                    ->columnSpanFull(),
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
                        ->default(fn () => \App\Models\Product::generateSku())
                        ->helperText('Auto-generated — edit to customize')
                        ->columnSpan(2)
                        ->suffixAction(
                            \Filament\Actions\Action::make('regenerate_sku')
                                ->icon('heroicon-o-arrow-path')
                                ->tooltip('Generate new SKU')
                                // Schemas\Components\Utilities\Set, not Forms\Set.
                                // Filament v5 moved it, and the old name still
                                // exists — so the type hint resolved, did not
                                // match what was passed, and 500'd on click.
                                ->action(function (\Filament\Schemas\Components\Utilities\Set $set) {
                                    $set('sku', \App\Models\Product::generateSku());
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
            ]);
    }

    public static function containerSettingsSection(): Section
    {
        return Section::make('Container / Case Settings')
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
                    // The model casts this to a boolean, and the options above
                    // are keyed 1/0 — so an existing item loaded as false
                    // matched no option, rendered with neither choice selected,
                    // and submitted null. The column is NOT NULL, so saving any
                    // existing item then died on a constraint violation.
                    ->formatStateUsing(fn ($state) => $state === null ? null : (int) (bool) $state)
                    ->dehydrateStateUsing(fn ($state) => (bool) $state)
                    ->inline()
                    ->extraAttributes(['class' => 'vx-choice-cards'])
                    ->live(),
                Repeater::make('childContents')
                    ->relationship('childContents', modifyQueryUsing: fn ($query) => $query->with('childItem'))
                    ->label('Items Inside This Container')
                    ->visible(fn (Get $get) => $get('is_container'))
                    // Starts empty. A repeater defaults to one row, and the
                    // item inside it is required — so ticking "this is a
                    // container" produced a blank line that had to be filled
                    // before the item could be saved at all. A sealed case
                    // whose contents nobody has itemised yet is the ordinary
                    // situation, not an incomplete form.
                    ->defaultItems(0)
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
            ]);
    }

    public static function classificationSection(): Section
    {
        return Section::make('Classification & Sourcing')
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
            ]);
    }

    public static function pricingSection(): Section
    {
        return Section::make('Pricing & Inventory Levels')
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
            ]);
    }

    public static function notesSection(): Section
    {
        return Section::make('Notes & Description')
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
            ]);
    }

    public static function initialStockSection(): Section
    {
        return Section::make('Initial Stock (Optional)')
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
            ]);
    }

    public static function stockByLocationSection(): Section
    {
        return Section::make('Stock by Location')
            ->icon('heroicon-o-map-pin')
            ->description('Current inventory levels by location')
            ->columnSpanFull()
            ->visible(fn (Get $get) => !!$get('id'))
            ->schema([
                Repeater::make('stock')
                    ->relationship('stock')
                    ->schema([
                        Grid::make(3)->schema([
                            // Was TextInput::make('location.name'), which
                            // rendered empty for every row: the repeater fills
                            // from the stock row's own attributes, and a dotted
                            // path there is read as a nested array key rather
                            // than as a relationship. inventory_location_id is
                            // a real column, so this resolves — and it reads
                            // every location, not just active ones, so stock
                            // sitting in a retired location still says where.
                            Select::make('inventory_location_id')
                                ->label('Location')
                                ->options(fn () => \App\Models\InventoryLocation::orderBy('name')->pluck('name', 'id')->toArray())
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

    /**
     * Product ids whose barcode or UPC is also carried by the other form —
     * a case sharing a scannable code with the singles inside it.
     *
     * Not SKU: that column is uniquely indexed, so two products cannot share
     * one and looking there found nothing by construction. Barcodes and UPCs
     * are not unique, and a vendor reusing one code for the case and for the
     * boxes inside it is exactly the case worth flagging — scanning it is then
     * ambiguous, and picking the wrong row is a twelve-fold counting error.
     *
     * Keyed by product id rather than by code, so a row can be checked without
     * knowing which of its two codes caused the clash. One query, memoised for
     * the request.
     *
     * @return array<int, int>
     */
    public static function productsSharingAScanCode(): array
    {
        // Memoised on the container rather than in a static: a static survives
        // the whole PHP process, so it would go stale in a queue worker, under
        // Octane, and between tests — which is exactly how it first went wrong
        // here, returning one test's answer to the next.
        $key = 'vx.products_sharing_scan_code';

        if (app()->bound($key)) {
            return app()->make($key);
        }

        // Every scannable code a product answers to, in one column.
        $codes = \App\Models\Product::query()
            ->selectRaw('barcode as code, id, is_container')
            ->whereNotNull('barcode')->where('barcode', '!=', '')
            ->unionAll(
                \App\Models\Product::query()
                    ->selectRaw('upc as code, id, is_container')
                    ->whereNotNull('upc')->where('upc', '!=', '')
            )
            ->unionAll(
                \Illuminate\Support\Facades\DB::table('product_identities')
                    ->join('products', 'products.id', '=', 'product_identities.product_id')
                    ->selectRaw('product_identities.value as code, products.id, products.is_container')
                    ->whereNotNull('product_identities.value')
                    ->where('product_identities.value', '!=', '')
            );

        // Codes answered to by both a container and a non-container.
        $ambiguous = \Illuminate\Support\Facades\DB::query()
            ->fromSub($codes, 'c')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(DISTINCT is_container) > 1');

        $ids = \Illuminate\Support\Facades\DB::query()
            ->fromSub($codes, 'c2')
            ->whereIn('code', $ambiguous)
            ->distinct()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        app()->instance($key, $ids);

        return $ids;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Leading the row rather than buried: scanning a list of
                // near-identical names is exactly the job a thumbnail does
                // better than text. Toggleable for anyone who wants it gone.
                // Picture first, then what the thing is, then its code — the
                // order the row is actually read in. SKU led the row before,
                // which is the one column nobody scans a list by.
                //
                // Sizing is set as an inline style rather than through
                // ->width()/->height(): extraImgAttributes replaces Filament's
                // own class list, which is where its width came from, so the
                // thumbnails rendered 36px tall and 0px wide — present in the
                // DOM and invisible on screen.
                \Filament\Tables\Columns\ImageColumn::make('image_path')
                    ->label('')
                    ->disk(\App\Models\Product::IMAGE_DISK)
                    // The brand mark rather than a gap: a missing image in a
                    // list is a hole in the layout, not information.
                    ->defaultImageUrl(fn () => \App\Models\Product::placeholderImageUrl())
                    ->extraImgAttributes(fn ($record) => [
                        'class' => 'rounded-md border border-gray-200 dark:border-gray-700 '
                            . ($record->hasImage() ? 'object-cover' : 'object-contain p-1 opacity-50'),
                        // flex:none because .fi-ta-image is a flex container:
                        // the img carried width:40px and still computed to 14px,
                        // shrunk to fit, which is why the thumbnails were in the
                        // DOM but invisible.
                        'style' => 'width:40px;height:40px;min-width:40px;flex:none;',
                    ])
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    // Containers get a marker so it's obvious which rows have
                    // something to open; the Contents action does the rest.
                    ->icon(fn ($record) => $record->is_container ? 'heroicon-m-archive-box' : null)
                    ->iconPosition(\Filament\Support\Enums\IconPosition::Before)
                    ->iconColor('primary')
                    ->description(fn ($record) => filled($record->description)
                        ? \Illuminate\Support\Str::limit($record->description, 70)
                        : null),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('—')
                    ->color('gray')
                    // Null rather than an em dash: Filament skips the second
                    // line entirely, so rows without a barcode stay one line
                    // tall instead of carrying an empty placeholder.
                    ->description(fn ($record) => filled($record->barcode) ? $record->barcode : null),
                // Case or single. A vendor may use one SKU for both, so the
                // row has to say which of the two it is rather than leaving it
                // to be inferred from the name.
                TextColumn::make('item_type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->is_container ? 'Case' : 'Item')
                    ->color(fn ($record) => $record->is_container ? 'warning' : 'gray')
                    ->icon(fn ($record) => $record->is_container ? 'heroicon-m-archive-box' : 'heroicon-m-cube')
                    // Only says anything when the same SKU exists in both
                    // forms, which is exactly when the distinction can bite.
                    ->description(fn ($record) => in_array((int) $record->id, static::productsSharingAScanCode(), true)
                        ? ($record->is_container ? 'Shares a barcode with the singles' : 'Shares a barcode with a case')
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
            // Layout and trigger come from TableFilterPresentation, applied to
            // every table in the panel. Eight filters here is what put it over
            // the line into a dialog: AboveContent pushed the table below the
            // fold, and the dropdown ran off the bottom of the window.
            ->filters([
                SelectFilter::make('item_type')
                    ->label('Type')
                    ->options(['case' => 'Cases / containers', 'single' => 'Single items'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'case'   => $query->where('is_container', true),
                        'single' => $query->where('is_container', false),
                        default  => $query,
                    }),
                Filter::make('shared_scan_code')
                    ->label('Barcode used by both a case and a single')
                    // The rows worth checking by hand: one scan, two meanings.
                    ->query(fn (Builder $query) => $query->whereIn('id', static::productsSharingAScanCode())),
                // "Where is it?" is the first question anyone asks of a stock
                // list and there was no way to ask it. Scoped to what the
                // viewer is allowed to see, so the filter can never become a
                // way to look somewhere they cannot.
                SelectFilter::make('location')
                    ->label('Location')
                    ->options(fn () => \App\Support\InventoryVisibility::isLimited(auth()->user())
                        ? InventoryLocation::whereIn('id', \App\Support\InventoryVisibility::locationIdsFor(auth()->user()))
                            ->orderBy('name')->pluck('name', 'id')->all()
                        : InventoryLocation::activeOptions())
                    ->multiple()
                    ->query(function (Builder $query, array $data) {
                        $ids = array_filter((array) ($data['values'] ?? []));

                        return $ids === []
                            ? $query
                            // Stock rows of zero are still rows, and an item
                            // that sat at a location and ran out is not at that
                            // location any more.
                            : $query->whereHas('stock', fn ($q) => $q
                                ->whereIn('inventory_location_id', $ids)
                                ->where('quantity', '>', 0));
                    }),
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
                // A streamer opening All Inventory wants their own shelves —
                // that is what they count, pick from and report against. The
                // wider list matters too, but only when they are looking for
                // something to request a transfer of, which is the rarer trip.
                //
                // So: their own by default, one toggle away from everything
                // they are allowed to see. Off, this filter adds nothing and
                // getEloquentQuery()'s visibility scope is what remains.
                Filter::make('mine_only')
                    ->label('My inventory only')
                    ->visible(fn () => \App\Support\InventoryVisibility::isLimited(auth()->user()))
                    ->default()
                    ->query(function (Builder $query) {
                        $user = auth()->user();
                        $own  = $user ? \App\Support\InventoryVisibility::ownLocationIds($user) : [];

                        // With no location of their own, this would filter the
                        // page down to nothing and read as an empty catalog.
                        if ($own === []) {
                            return $query;
                        }

                        return $query->whereHas('stock', fn ($q) => $q
                            ->whereIn('inventory_location_id', $own)
                            ->where('quantity', '>', 0));
                    }),
                Filter::make('is_active')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->where('is_active', true))
                    ->default(),
                QueryBuilder::make()
                    ->label('Advanced Filters')
                    // Its rules are full-width controls; squeezed into one
                    // column of three it is unusable.
                    ->columnSpanFull()
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
                // Turns a container's recorded contents into real stock: one
                // case out, twelve boxes in. Only shown when there is
                // something to break it into.
                TableAction::make('break_container')
                    ->label('Break Case')
                    ->icon('heroicon-o-scissors')
                    ->color('warning')
                    ->size('sm')
                    ->iconButton()
                    ->tooltip('Break this container into its contents')
                    ->visible(fn ($record) => (bool) $record->is_container
                        && $record->childContents()->exists())
                    ->modalHeading(fn ($record) => 'Break ' . $record->name)
                    ->modalDescription('Deducts the containers and adds their contents as stock at the same location.')
                    ->modalSubmitActionLabel('Break')
                    ->form([
                        Select::make('location_id')
                            ->label('Location')
                            ->options(fn ($record) => InventoryStock::query()
                                ->where('inventory_item_id', $record->getKey())
                                ->where('quantity', '>', 0)
                                ->with('location')
                                ->get()
                                ->mapWithKeys(fn ($stock) => [
                                    $stock->inventory_location_id =>
                                        ($stock->location?->name ?? 'Location') . ' — ' . (int) $stock->quantity . ' on hand',
                                ])
                                ->toArray())
                            ->required()
                            ->helperText('Only locations holding this container are listed.'),
                        TextInput::make('count')
                            ->label('How many to break')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Placeholder::make('contents_preview')
                            ->label('Each one produces')
                            ->content(fn ($record) => $record->childContents()->with('childItem')->get()
                                ->map(fn ($l) => (int) $l->quantity_per_parent . ' × ' . ($l->childItem?->name ?? 'unknown'))
                                ->implode(', ') ?: '—'),
                    ])
                    ->action(function (InventoryItem $record, array $data): void {
                        try {
                            $result = app(\App\Services\ContainerBreakdownService::class)->break(
                                $record,
                                InventoryLocation::findOrFail($data['location_id']),
                                (int) $data['count'],
                            );
                        } catch (\Throwable $e) {
                            Notification::make()->title('Could not break this container')
                                ->body($e->getMessage())->danger()->send();

                            return;
                        }

                        $summary = collect($result['produced'])
                            ->map(fn ($p) => $p['quantity'] . ' × ' . $p['name'])
                            ->implode(', ');

                        Notification::make()
                            ->title('Broke ' . $result['containers_broken'] . ' × ' . $result['container'])
                            ->body('Added ' . $summary)
                            ->success()
                            ->send();
                    }),
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
                    // One page, three operations. These were three modals
                    // that each asked you to decide a number against figures
                    // the dialog itself was covering — what is here now, where,
                    // and what it will be afterwards. On a page those stay on
                    // screen while the decision is made, and the three stop
                    // being three places for the same mistake to be made
                    // differently.
                    Action::make('manage_stock')
                        ->label('Move or correct stock')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('warning')
                        ->url(fn (InventoryItem $record) => static::getUrl('stock', ['record' => $record])),
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
            'stock' => Pages\ManageStock::route('/{record}/stock'),
        ];
    }
}

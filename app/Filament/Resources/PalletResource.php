<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Resources\PalletResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Vendor;
use App\Services\ReceivingService;
use App\Support\AdminModules;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class PalletResource extends Resource
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'purchasing';
    protected static string $featureSlug = 'pallets';

    protected static ?string $model = Pallet::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-inbox-stack';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
    }

    public static function getNavigationLabel(): string
    {
        return 'Receive Inventory';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('nav_badge:pallets_active', 60, fn () =>
            Pallet::whereIn('status', ['pending', 'receiving'])->count()
        );
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vendor'])->withCount('lines');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pallet Details')->schema([
                Grid::make(2)->schema([
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(fn () => Vendor::activeOptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('reference')
                        ->label('PO / Reference #')
                        ->maxLength(255),
                    DatePicker::make('received_date')
                        ->label('Received Date'),
                    TextInput::make('total_cost')
                        ->label('Total Invoice Cost ($)')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0),
                    Select::make('status')
                        ->options(Pallet::statusLabels())
                        ->default('pending')
                        ->required(),
                ]),
                Textarea::make('notes')->rows(2)->columnSpanFull(),
            ]),

            Section::make('Manifest Lines')
                ->description('Enter each product line from the pallet manifest. Map each to an inventory item after saving.')
                ->schema([
                    Repeater::make('lines')
                        ->relationship('lines')
                        ->schema([
                            Grid::make(12)->schema([
                                TextInput::make('description')
                                    ->label('Description / Product Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(6),
                                TextInput::make('case_count')
                                    ->label('Cases')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('quantity_per_case')
                                    ->label('Units / Box')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->required()
                                    ->helperText('1 if selling sealed boxes')
                                    ->columnSpan(2),
                                TextInput::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->minValue(0)
                                    ->columnSpan(2),
                                Select::make('inventory_item_id')
                                    ->label('Map to Inventory Item')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search) => InventoryItem::where('is_active', true)
                                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                                        ->orderBy('name')
                                        ->limit(30)
                                        ->pluck('name', 'id')
                                        ->toArray())
                                    ->getOptionLabelUsing(fn ($value) => InventoryItem::find($value)?->name ?? $value)
                                    ->placeholder('Search by name or SKU…')
                                    ->columnSpan(8),
                                Select::make('inventory_location_id')
                                    ->label('Receive Into Location')
                                    ->options(fn () => InventoryLocation::activeOptions())
                                    ->searchable()
                                    ->placeholder('Select destination…')
                                    ->columnSpan(4),
                            ]),
                        ])
                        ->orderColumn('line_number')
                        ->addActionLabel('Add Line')
                        ->reorderable('line_number')
                        ->collapsible()
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('received_date')
                    ->label('Received')
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending'   => 'gray',
                        'receiving' => 'warning',
                        'received'  => 'info',
                        'processed' => 'success',
                        default     => 'gray',
                    }),
                TextColumn::make('lines_count')
                    ->label('Lines')
                    ->sortable(),
                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('USD')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateIcon('heroicon-o-inbox-stack')
            ->emptyStateHeading('No pallets yet')
            ->emptyStateDescription('Log an incoming pallet from a vendor, map its lines to inventory, then receive by barcode or all at once.')
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()->label('Receive a pallet'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Pallet::statusLabels()),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->options(fn () => Vendor::activeOptions()),
            ])
            ->actions([
                Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->url(fn (Pallet $record) => static::getUrl('receive', ['record' => $record]))
                    ->visible(fn (Pallet $record) => in_array($record->status, ['pending', 'receiving'])),
                Action::make('import_manifest')
                    ->label('Import Manifest')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->url(fn (Pallet $record) => static::getUrl('import-manifest', ['record' => $record]))
                    ->visible(fn (Pallet $record) => in_array($record->status, ['pending', 'receiving'])),
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('received_date', 'desc')
            ->striped()
            ->deferLoading()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index'           => Pages\ListPallets::route('/'),
            'create'          => Pages\CreatePallet::route('/create'),
            'view'            => Pages\ViewPallet::route('/{record}'),
            'edit'            => Pages\EditPallet::route('/{record}/edit'),
            'receive'         => Pages\ReceivePallet::route('/{record}/receive'),
            'import-manifest' => Pages\ImportManifest::route('/{record}/import-manifest'),
        ];
    }
}

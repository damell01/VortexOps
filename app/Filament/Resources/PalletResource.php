<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\PalletResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Vendor;
use App\Services\ReceivingService;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class PalletResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'purchasing';

    protected static ?string $model = Pallet::class;

    /**
     * Pallet is soft-deletable, so deleting one never fires the cascade
     * on pallet_lines/inventory_cases (soft delete is an UPDATE, not a real
     * DELETE — the FK trigger never runs). Still block once any cases have
     * actually been received, so a receiving session in progress can't
     * vanish out from under whoever's scanning it.
     */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && $record->receivedCasesCount() === 0;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference'];
    }

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
            Pallet::whereIn('status', ['staged', 'receiving'])->count()
        );
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['vendor', 'lines', 'cases'])
            ->withCount('lines');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pallet Details')
                ->description('Vendor, purchase order, and shipment information')
                ->columnSpanFull()->schema([
                Grid::make(3)->schema([
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
                    TextInput::make('shipping_cost')
                        ->label('Shipping Cost ($)')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->helperText('Automatically allocated to items based on quantity received'),
                    Select::make('status')
                        ->options(Pallet::statusLabels())
                        ->default('staged')
                        ->required(),
                ]),
                Grid::make(3)->schema([
                    TextInput::make('carrier')
                        ->maxLength(255),
                    TextInput::make('tracking_number')
                        ->maxLength(255)
                        ->copyable(),
                    DatePicker::make('expected_delivery_date')
                        ->label('Expected Delivery'),
                ]),
                Textarea::make('notes')->rows(2)->columnSpanFull(),
            ]),

            Section::make('Manifest Lines')
                ->description('Enter each product line from the pallet manifest. Map each to an inventory item after saving.')
                ->columnSpanFull()
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

            Section::make('Media & Attachments')
                ->description('Upload photos, documents, and other evidence tied to this pallet')
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('new_attachments')
                        ->label('Upload Files')
                        ->multiple()
                        ->directory('pallets')
                        ->visibility('public')
                        ->maxSize(5120) // 5MB per file
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'image/gif'])
                        ->helperText('Upload photos, PDFs, or documents (max 5MB each). For use during receiving process.'),
                ]),

            Section::make('Receiving Details')
                ->description('Captured during the receiving workflow')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('received_by_name')
                        ->label('Received By (Name)')
                        ->maxLength(255)
                        ->columnSpan(1),
                    TextInput::make('signature_path')
                        ->label('Signature File Path')
                        ->maxLength(255)
                        ->disabled()
                        ->columnSpan(1),
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
                    ->color(fn ($state) => StatusColor::for($state)),
                TextColumn::make('receiving_progress')
                    ->label('Receiving Progress')
                    ->state(function (Pallet $record): string {
                        $total = $record->totalCasesCount();
                        $received = $record->receivedCasesCount();
                        $percent = $total > 0 ? intval(($received / $total) * 100) : 0;
                        return "{$received}/{$total} cases ({$percent}%)";
                    })
                    ->visible(fn (?Pallet $record) => $record && in_array($record->status, ['receiving', 'received', 'processed'])),
                TextColumn::make('next_action')
                    ->label('Next Action')
                    ->state(fn (Pallet $record): string => match ($record->status) {
                        'pending' => 'Enter manifest lines',
                        'shipped' => 'Receive pallet',
                        'receiving' => 'Continue receiving cases',
                        'received' => 'Mark as processed',
                        'processed' => 'Complete',
                        default => 'Review',
                    })
                    ->badge()
                    ->color(fn (Pallet $record): string => match ($record->status) {
                        'pending', 'shipped' => 'warning',
                        'receiving' => 'info',
                        'received' => 'success',
                        'processed' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->copyable()
                    ->placeholder('—')
                    ->description(fn (Pallet $record) => $record->carrier)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expected_delivery_date')
                    ->label('Expected')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Action::make('stage')
                    ->label('Stage Manifest')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->url(fn (Pallet $record) => static::getUrl('stage', ['record' => $record]))
                    ->visible(fn (Pallet $record) => $record->status === 'staged'),
                Action::make('receive')
                    ->label('Start Receiving')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->url(fn (Pallet $record) => static::getUrl('receive', ['record' => $record]))
                    ->visible(fn (Pallet $record) => in_array($record->status, ['staged', 'receiving'])),
                Action::make('import_manifest')
                    ->label('Import CSV')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->url(fn (Pallet $record) => static::getUrl('import-manifest', ['record' => $record]))
                    ->visible(fn (Pallet $record) => in_array($record->status, ['staged'])),
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (Pallet $record) => static::canDelete($record))
                    ->tooltip(fn (Pallet $record) => static::canDelete($record) ? null : 'Cases have already been received on this pallet.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $deletable = $records->filter(fn (Pallet $record) => static::canDelete($record));
                            $blocked   = $records->count() - $deletable->count();

                            $deletable->each->delete();

                            if ($blocked > 0) {
                                Notification::make()
                                    ->title($deletable->count() . ' pallet(s) deleted')
                                    ->body("{$blocked} skipped — cases already received.")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()->title($deletable->count() . ' pallet(s) deleted')->success()->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
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
            'stage'           => Pages\StagePallet::route('/{record}/stage'),
            'receive'         => Pages\ReceivePallet::route('/{record}/receive'),
            'import-manifest' => Pages\ImportManifest::route('/{record}/import-manifest'),
        ];
    }
}

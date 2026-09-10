<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\PalletResource\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Vendor;
use App\Services\PalletArchiveService;
use App\Services\ReceivingReportService;
use App\Services\ReceivingService;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\View;
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
use Illuminate\Support\Facades\Storage;

class PalletResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'purchasing';
    protected static ?string $model = Pallet::class;

    /** Active, untouched pallets can still use Filament's simple soft delete. */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && $record->receivedCasesCount() === 0;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getGloballySearchableAttributes(): array { return ['reference']; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-inbox-stack'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return AdminModules::navigationGroupFor('purchasing'); }
    public static function getNavigationLabel(): string { return 'Receive Inventory'; }
    public static function getNavigationSort(): ?int { return 2; }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('nav_badge:pallets_active', 60, fn () =>
            Pallet::whereIn('status', ['staged', 'receiving'])->count()
        );
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('vendor')
            ->withCount('lines')
            ->withCount(['cases as received_cases_count' => fn ($query) => $query->where('inventory_cases.status', '!=', 'expected')])
            ->withCount('missingItems as missing_items_count')
            ->withSum('lines as expected_cases_sum', 'case_count')
            ->withSum('missingItems as missing_items_value', 'total_value');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pallet Details')
                ->description('Vendor, purchase order, and shipment information')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')
                            ->label('Pallet Name')
                            ->placeholder('e.g. Topps Chrome — August')
                            ->helperText('What you would call it out loud. Optional.')
                            ->maxLength(255),
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->options(fn () => Vendor::activeOptions())
                            ->searchable()
                            ->required(),
                        TextInput::make('reference')
                            ->label('PO / Reference #')
                            ->helperText("The vendor's number, so it keeps matching their paperwork.")
                            ->maxLength(255),
                        DatePicker::make('received_date')->label('Received Date'),
                        TextInput::make('total_cost')->label('Total Invoice Cost ($)')->numeric()->prefix('$')->minValue(0),
                        TextInput::make('shipping_cost')
                            ->label('Shipping Cost ($)')->numeric()->prefix('$')->minValue(0)
                            ->helperText('Spread across the items by quantity when the pallet is received'),
                        TextInput::make('payment_fees')
                            ->label('Payment Fees ($)')->numeric()->prefix('$')->minValue(0)->default(0)
                            ->helperText('Card, PayPal or wire charges — spread across the items the same way'),
                        Select::make('status')
                            ->options(Pallet::statusLabels())
                            ->default('staged')
                            ->required(),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('carrier')->maxLength(255),
                        TextInput::make('tracking_number')->maxLength(255)->copyable(),
                        DatePicker::make('expected_delivery_date')->label('Expected Delivery'),
                    ]),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),

            Section::make('Manifest Lines')
                ->description('One line per thing on the pallet.')
                ->columnSpanFull()
                ->schema([
                    \Filament\Schemas\Components\View::make('filament.components.manifest-lines-link')->columnSpanFull(),
                ]),

            Section::make('Media & Attachments')
                ->description('Upload photos, documents, and other evidence tied to this pallet')
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('new_attachments')
                        ->label('Upload Files')
                        ->multiple()
                        ->disk(\App\Services\PalletAttachmentService::DISK)
                        ->directory('pallets')
                        ->visibility('public')
                        ->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'image/gif'])
                        ->helperText('Upload photos, PDFs, or documents (max 5MB each). For use during receiving process.'),
                    View::make('filament.components.photo-capture-button'),
                ]),

            Section::make('Receiving Details')
                ->description('Captured during the receiving workflow')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('received_by_name')
                        ->label('Received By (Name)')
                        ->maxLength(255)
                        ->columnSpan(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendor.name')->label('Vendor')->searchable()->sortable(),
                TextColumn::make('name')
                    ->label('Pallet')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Pallet $record) => $record->reference)
                    ->state(fn (Pallet $record) => $record->displayName()),
                TextColumn::make('reference')
                    ->label('Reference')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('received_date')->label('Received')->date('M j, Y')->sortable()->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => in_array($state, ['received', 'processed'], true) ? 'Complete' : ucfirst((string) $state))
                    ->color(fn ($state) => StatusColor::for($state)),
                TextColumn::make('receiving_progress')
                    ->label('Receiving Progress')
                    ->state(function (Pallet $record): string {
                        $total = $record->totalCasesCount();
                        $received = $record->receivedCasesCount();
                        $percent = $total > 0 ? intval(($received / $total) * 100) : 0;
                        return "{$received}/{$total} ({$percent}%)";
                    })
                    ->visible(fn (?Pallet $record) => $record && in_array($record->status, ['receiving', 'received', 'processed'])),
                TextColumn::make('missing_items')
                    ->label('Missing Items')
                    ->state(function (Pallet $record): string {
                        $missing = (int) ($record->missing_items_count ?? 0);
                        if ($missing === 0) return '—';
                        return $missing . ' item(s) · $' . number_format((float) ($record->missing_items_value ?? 0), 2);
                    })
                    ->badge()->color('warning')
                    ->visible(fn (?Pallet $record) => $record && (int) ($record->missing_items_count ?? 0) > 0),
                TextColumn::make('next_action')
                    ->label('Next Action')
                    ->state(fn (Pallet $record): string => match ($record->status) {
                        'pending' => 'Enter manifest lines',
                        'shipped' => 'Receive pallet',
                        'staged' => 'Start receiving',
                        'receiving' => 'Continue receiving',
                        'received', 'processed' => 'Complete',
                        default => 'Review',
                    })
                    ->badge()
                    ->color(fn (Pallet $record): string => match ($record->status) {
                        'pending', 'shipped', 'staged' => 'warning',
                        'receiving' => 'info',
                        'received', 'processed' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('tracking_number')
                    ->label('Tracking')->copyable()->placeholder('—')->description(fn (Pallet $record) => $record->carrier)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expected_delivery_date')
                    ->label('Expected')->date('M j, Y')->placeholder('—')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lines_count')->label('Lines')->sortable(),
                TextColumn::make('total_cost')->label('Total Cost')->money('USD')->placeholder('—'),
                TextColumn::make('created_at')->dateTime('M j, Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateIcon('heroicon-o-inbox-stack')
            ->emptyStateHeading('No pallets yet')
            ->emptyStateDescription('Log an incoming pallet from a vendor, map its lines to inventory, then receive by barcode or all at once.')
            ->emptyStateActions([\Filament\Actions\CreateAction::make()->label('Receive a pallet')])
            ->filters([
                SelectFilter::make('status')->options(Pallet::statusLabels()),
                SelectFilter::make('vendor_id')->label('Vendor')->options(fn () => Vendor::activeOptions()),
            ])
            ->actions([
                Action::make('open')
                    ->label(fn (Pallet $record) => $record->status === 'staged' ? 'Stage' : 'Receive')
                    ->icon(fn (Pallet $record) => $record->status === 'staged' ? 'heroicon-o-clipboard-document-list' : 'heroicon-o-inbox-arrow-down')
                    ->color(fn (Pallet $record) => $record->status === 'staged' ? 'info' : 'success')
                    ->button()
                    ->url(fn (Pallet $record) => static::getUrl('view', ['record' => $record]))
                    ->visible(fn (Pallet $record) => in_array($record->status, ['pending', 'staged', 'shipped', 'receiving'])),

                Action::make('export_receiving_report')
                    ->label('PDF')
                    ->tooltip('Export receiving report')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->iconButton()
                    ->visible(fn (Pallet $record) => in_array($record->status, ['received', 'processed'], true))
                    ->action(function (Pallet $record) {
                        try {
                            $filePath = app(ReceivingReportService::class)->generatePalletReport($record);
                            return response()->download(
                                Storage::disk('public')->path($filePath),
                                'pallet-' . ($record->reference ?: $record->id) . '.pdf'
                            );
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()->title('Could not generate report')->body($e->getMessage())->danger()->send();
                        }
                    }),

                DeleteAction::make()
                    ->label('Delete Pallet')
                    ->iconButton()
                    ->tooltip('Delete pallet')
                    ->visible(fn (Pallet $record) => static::canDelete($record)),

                Action::make('archive_received_pallet')
                    ->label('Delete Pallet')
                    ->tooltip('Delete pallet and safely reverse received inventory')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->iconButton()
                    ->visible(fn (Pallet $record) => (auth()->user()?->isAdmin() ?? false) && $record->receivedCasesCount() > 0)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Pallet $record) => 'Delete ' . $record->displayName() . '?')
                    ->modalDescription(fn (Pallet $record) => app(PalletArchiveService::class)->previewText($record))
                    ->modalSubmitActionLabel('Delete & reverse inventory')
                    ->action(function (Pallet $record) {
                        try {
                            $preview = app(PalletArchiveService::class)->archive($record);
                            Notification::make()
                                ->title('Pallet deleted')
                                ->body(number_format(collect($preview['effects'])->sum('quantity'), 2) . ' inventory units reversed. Undo is available from Archived Pallets.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Pallet was not deleted')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                \Filament\Actions\ActionGroup::make([
                    ViewAction::make(),
                    Action::make('scanning_station')
                        ->label('Scanning Station')
                        ->icon('heroicon-o-qr-code')
                        ->url(fn (Pallet $record) => static::getUrl('receive', ['record' => $record]))
                        ->visible(fn (Pallet $record) => in_array($record->status, ['staged', 'receiving'])),
                    EditAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $deletable = $records->filter(fn (Pallet $record) => static::canDelete($record));
                            $blocked = $records->count() - $deletable->count();
                            $deletable->each->delete();

                            if ($blocked > 0) {
                                Notification::make()
                                    ->title($deletable->count() . ' pallet(s) deleted')
                                    ->body("{$blocked} skipped — pallets with received inventory must be deleted one at a time so VortexOps can show and reverse their inventory impact safely.")
                                    ->warning()->send();
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
            'index' => Pages\ListPallets::route('/'),
            'create' => Pages\CreatePallet::route('/create'),
            'view' => Pages\ViewPallet::route('/{record}'),
            'edit' => Pages\EditPallet::route('/{record}/edit'),
            'stage' => Pages\StagePallet::route('/{record}/stage'),
            'add-lines' => Pages\AddPalletLines::route('/{record}/add-lines'),
            'items' => Pages\PalletItems::route('/{record}/items'),
            'receive' => Pages\ReceivePallet::route('/{record}/receive'),
            'import-manifest' => Pages\ImportManifest::route('/{record}/import-manifest'),
        ];
    }
}

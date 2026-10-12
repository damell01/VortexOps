<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Jobs\GeneratePalletReceivingReport;
use App\Models\Pallet;
use App\Services\PalletArchiveService;
use App\Services\ReceivingReportService;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PalletReceivingHistory extends Page implements HasTable
{
    use HasModuleAccess, InteractsWithTable;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Receiving History';

    #[\Livewire\Attributes\Url]
    public ?int $vendorId = null;

    public static function getNavigationGroup(): string|\UnitEnum|null { return AdminModules::navigationGroupFor('inventory'); }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-inbox-stack'; }
    public static function shouldRegisterNavigation(): bool
    {
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) return false;
        return false;
    }
    public static function getNavigationLabel(): string { return 'Pallet History'; }
    public static function getNavigationSort(): ?int { return 999; }
    public function getView(): string { return 'filament.pages.pallet-receiving-history'; }
    public function getMaxContentWidth(): Width { return Width::Full; }
    public function getSubheading(): ?string { return 'Find received and unfinished deliveries, compare expected and received cases, and review costs and attachments.'; }

    public static function historyQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Pallet::query()->with(['vendor', 'lines.location'])
            ->withSum('lines as expected_cases_sum', 'case_count')
            ->withCount(['attachments', 'missingItems', 'cases as received_cases_count' => fn ($q) => $q->where('status', '!=', 'expected')])
            ->whereIn('status', ['receiving', 'received', 'processed']);
    }

    public static function receiptColumns(): array
    {
        return [\Filament\Tables\Columns\Layout\Stack::make([
            TextColumn::make('received_date')->label('Received')->date('M j, Y')->sortable()->placeholder('In progress'),
            TextColumn::make('name')->label('Pallet / PO')->state(fn (Pallet $record) => $record->displayName())->description(fn (Pallet $record) => $record->reference ?: 'No reference')->weight('semibold')->searchable(['name', 'reference']),
            TextColumn::make('vendor.name')->label('Vendor')->searchable(),
            TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => in_array($state, ['received', 'processed'], true) ? 'Complete' : 'In progress')->color(fn (string $state) => in_array($state, ['received', 'processed'], true) ? 'success' : 'warning'),
            TextColumn::make('expected_cases_sum')->label('Expected cases')->description('Expected cases')->numeric()->default(0),
            TextColumn::make('received_cases_count')->label('Received cases')->description('Received cases')->numeric(),
            TextColumn::make('remaining')->label('Remaining cases')->description('Remaining cases')->state(fn (Pallet $record) => max(0, (int) $record->expected_cases_sum - (int) $record->received_cases_count)),
            TextColumn::make('missing_items_count')->label('Discrepancies')->numeric()->description('Recorded missing-item reports'),
            TextColumn::make('locations')->label('Locations')->description('Receiving locations')->state(fn (Pallet $record) => $record->lines->map(fn ($line) => $line->location?->name)->filter()->unique()->implode(', '))->wrap()->placeholder('Not assigned'),
            TextColumn::make('total_cost')->label('Recorded delivery cost')->description('Recorded delivery cost')->money('USD')->placeholder('Not recorded'),
            TextColumn::make('attachments_count')->label('Files')->description('Attached files')->numeric(),
        ])];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive')->label('Receive Inventory')->icon('heroicon-o-inbox-arrow-down')->url(fn () => \App\Filament\Resources\PalletResource::getUrl()),
            Action::make('guide')->label('Inventory Guide')->icon('heroicon-o-book-open')->url(fn () => InventoryGuide::getUrl())->visible(fn () => InventoryGuide::canAccess()),
            Action::make('archived')
                ->label('Archived Pallets')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->url(fn () => ArchivedPallets::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::historyQuery()->when($this->vendorId, fn ($q) => $q->where('vendor_id', $this->vendorId)))
            ->defaultSort('received_date', 'desc')
            ->columns(static::receiptColumns())
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('vendor_id')->label('Vendor')->relationship('vendor', 'name')->searchable()->preload(),
                \Filament\Tables\Filters\SelectFilter::make('status')->label('Progress')->options(['receiving'=>'In progress', 'received'=>'Complete', 'processed'=>'Complete (legacy)']),
                \Filament\Tables\Filters\Filter::make('received_dates')->schema([
                    \Filament\Forms\Components\DatePicker::make('from')->label('Received from'),
                    \Filament\Forms\Components\DatePicker::make('until')->label('Received through'),
                ])->query(fn ($query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('received_date', '>=', $date))
                    ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('received_date', '<=', $date))),
            ])
            ->emptyStateHeading('No receiving history matches')
            ->emptyStateDescription('Clear filters or start receiving a delivery. Staged pallets stay on Receive Inventory.')
            ->actions([
                Action::make('open')->label('Open delivery')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Pallet $record) => \App\Filament\Resources\PalletResource::getUrl('view', ['record'=>$record])),
                Action::make('resume')->label('Resume receiving')->icon('heroicon-o-play')->visible(fn (Pallet $record) => $record->status === 'receiving')->url(fn (Pallet $record) => \App\Filament\Resources\PalletResource::getUrl('receive', ['record'=>$record])),
                Action::make('view_items')
                    ->label('Items')
                    ->tooltip('View received items')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->iconButton()
                    ->modalWidth('5xl')
                    ->modalHeading(fn (Pallet $record) => 'Received Items — ' . $record->displayName())
                    ->modalContent(fn (Pallet $record) => view('modals.pallet-items', ['pallet' => $record->load(['lines.inventoryItem', 'vendor'])]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('export_pdf')->visible(fn (Pallet $record) => in_array($record->status, ['received', 'processed'], true))
                    ->label('PDF')
                    ->tooltip('Download receiving PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->iconButton()
                    ->action(function (Pallet $record) {
                        try {
                            $reports = app(ReceivingReportService::class);
                            $filePath = $reports->preparedPalletReportPath($record);

                            if (! Storage::disk('public')->exists($filePath)) {
                                GeneratePalletReceivingReport::dispatch($record->id);
                                Notification::make()
                                    ->title('Report is preparing')
                                    ->body('It was not pre-generated yet. It is being built now; try the PDF button again in a moment.')
                                    ->warning()
                                    ->send();
                                return null;
                            }

                            return response()->download(
                                Storage::disk('public')->path($filePath),
                                'pallet-' . ($record->reference ?: $record->id) . '.pdf'
                            );
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()->title('Could not download report')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('archive')->visible(fn (Pallet $record) => in_array($record->status, ['received', 'processed'], true))
                    ->label('Archive')
                    ->tooltip('Remove pallet and reverse its received inventory')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading(fn (Pallet $record) => 'Archive ' . $record->displayName() . '?')
                    ->modalDescription(fn (Pallet $record) => app(PalletArchiveService::class)->previewText($record))
                    ->modalSubmitActionLabel('Archive & reverse inventory')
                    ->action(function (Pallet $record) {
                        try {
                            $preview = app(PalletArchiveService::class)->archive($record);
                            Notification::make()
                                ->title('Pallet archived')
                                ->body(number_format(collect($preview['effects'])->sum('quantity'), 2) . ' inventory units reversed. You can restore it from Archived Pallets.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Pallet was not archived')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->striped();
    }
}


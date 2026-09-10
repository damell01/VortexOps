<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Pallet;
use App\Services\ReceivingReportService;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Actions\Action;
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
    protected static ?string $title = 'Received Pallets';

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
    public function getSubheading(): ?string { return 'Review completed pallet receipts, inspect received items, and export a branded receiving report.'; }

    public function table(Table $table): Table
    {
        return $table
            ->query(Pallet::query()->with(['vendor', 'lines'])->whereIn('status', ['received', 'processed']))
            ->defaultSort('received_date', 'desc')
            ->columns([
                TextColumn::make('received_date')->label('Received')->date('M j, Y')->sortable()->placeholder('—'),
                TextColumn::make('name')->label('Pallet / PO')->state(fn (Pallet $record) => $record->displayName())->description(fn (Pallet $record) => $record->reference ?: 'No reference')->weight('semibold')->searchable(['name', 'reference'])->url(fn (Pallet $record) => route('filament.admin.resources.pallets.view', ['record' => $record->id])),
                TextColumn::make('vendor.name')->label('Vendor')->searchable(['vendors.name']),
                TextColumn::make('line_items_total')->label('Items')->alignCenter()->state(fn (Pallet $record) => $record->lines->count()),
                TextColumn::make('total_cost')->label('Total Cost')->money('USD', locale: 'en_US')->alignRight()->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state) => match ($state) {'received' => 'success','processed' => 'info',default => 'gray'}),
            ])
            ->actions([
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
                Action::make('export_pdf')
                    ->label('PDF')
                    ->tooltip('Download receiving PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->iconButton()
                    ->action(function (Pallet $record) {
                        try {
                            $filePath = app(ReceivingReportService::class)->generatePalletReport($record);
                            return response()->download(Storage::disk('public')->path($filePath), "pallet-{$record->reference}.pdf");
                        } catch (\Exception $e) { report($e); }
                    }),
            ])
            ->bulkActions([])
            ->striped();
    }
}

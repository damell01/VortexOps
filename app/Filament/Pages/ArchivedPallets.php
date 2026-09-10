<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Pallet;
use App\Services\PalletArchiveService;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ArchivedPallets extends Page implements HasTable
{
    use HasModuleAccess, InteractsWithTable;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Archived Pallets';
    protected static ?string $slug = 'archived-pallets';

    public static function shouldRegisterNavigation(): bool { return false; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return AdminModules::navigationGroupFor('inventory'); }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-trash'; }
    public function getView(): string { return 'filament.pages.archived-pallets'; }
    public function getMaxContentWidth(): Width { return Width::Full; }
    public function getSubheading(): ?string { return 'Restore pallets that were archived by mistake. Inventory credited by the pallet is restored with it.'; }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Received Pallets')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => PalletReceivingHistory::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Pallet::onlyTrashed()->with(['vendor', 'lines']))
            ->defaultSort('deleted_at', 'desc')
            ->columns([
                TextColumn::make('deleted_at')->label('Archived')->dateTime('M j, Y g:i A')->sortable(),
                TextColumn::make('name')->label('Pallet / PO')
                    ->state(fn (Pallet $record) => $record->displayName())
                    ->description(fn (Pallet $record) => $record->reference ?: 'No reference')
                    ->weight('semibold'),
                TextColumn::make('vendor.name')->label('Vendor'),
                TextColumn::make('received_date')->label('Received')->date('M j, Y')->placeholder('—'),
                TextColumn::make('lines_count')->label('Items')->alignCenter(),
                TextColumn::make('total_cost')->label('Recorded Cost')->money('USD')->alignRight()->placeholder('—'),
            ])
            ->actions([
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading(fn (Pallet $record) => 'Restore ' . $record->displayName() . '?')
                    ->modalDescription('The pallet will return to Received Pallets and the inventory quantities that were reversed when it was archived will be added back.')
                    ->action(function (Pallet $record) {
                        try {
                            app(PalletArchiveService::class)->restore($record);
                            Notification::make()
                                ->title('Pallet restored')
                                ->body($record->displayName() . ' and its receipt inventory are active again.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()->title('Could not restore pallet')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->emptyStateIcon('heroicon-o-trash')
            ->emptyStateHeading('No archived pallets')
            ->emptyStateDescription('Pallets you archive will stay recoverable here.')
            ->bulkActions([]);
    }
}

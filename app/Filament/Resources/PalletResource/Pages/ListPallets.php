<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Pages\ArchivedPallets;
use App\Filament\Pages\PalletReceivingHistory;
use App\Filament\Resources\PalletResource;
use App\Filament\Widgets\PalletReceivingOverviewWidget;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPallets extends ListRecords
{
    protected static string $resource = PalletResource::class;

    public function getTitle(): string
    {
        return 'Receive Inventory';
    }

    public function getSubheading(): ?string
    {
        return 'One place to receive pallets, review completed receipts, export reports, delete bad entries safely, and restore archived pallets.';
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active Receiving')
                ->icon('heroicon-o-inbox-arrow-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', ['received', 'processed'])),
            'completed' => Tab::make('Received / Complete')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['received', 'processed'])),
            'all' => Tab::make('All Pallets')
                ->icon('heroicon-o-rectangle-stack'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Pallet')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn () => PalletResource::getUrl('create')),

            Action::make('history')
                ->label('Received Pallets')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->url(fn () => PalletReceivingHistory::getUrl()),

            Action::make('archived')
                ->label('Archived / Undo')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn () => ArchivedPallets::getUrl()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PalletReceivingOverviewWidget::class,
        ];
    }
}

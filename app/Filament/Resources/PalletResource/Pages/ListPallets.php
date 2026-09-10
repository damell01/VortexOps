<?php

namespace App\Filament\Resources\PalletResource\Pages;

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
        return 'Work the pallets that still need attention. Completed receives stay out of the way unless you open Received / Completed or Receiving History.';
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active Receiving')
                ->icon('heroicon-o-inbox-arrow-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', ['received', 'processed'])),
            'completed' => Tab::make('Received / Completed')
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
                ->label('New Shipment / Pallet')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn () => PalletResource::getUrl('create')),

            Action::make('history')
                ->label('Received Pallets')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->url(fn () => PalletReceivingHistory::getUrl()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PalletReceivingOverviewWidget::class,
        ];
    }
}

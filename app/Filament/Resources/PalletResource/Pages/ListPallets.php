<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Filament\Widgets\PalletReceivingOverviewWidget;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListPallets extends ListRecords
{
    protected static string $resource = PalletResource::class;

    public function getTitle(): string
    {
        return 'Receive Inventory';
    }

    public function getSubheading(): ?string
    {
        return 'Track each vendor delivery from staged manifest → active receiving → received → processed. Open the pallet you are unloading and work from its expected cases.';
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
                ->label('Receiving History')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->url('/admin/pallet-receiving-history'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PalletReceivingOverviewWidget::class,
        ];
    }
}

<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
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
        return 'Each pallet is one vendor delivery. Open the shipment you are unloading, then scan boxes until the received count matches what actually arrived.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Shipment / Pallet')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn () => PalletResource::getUrl('create')),
        ];
    }
}

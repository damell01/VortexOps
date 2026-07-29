<?php

namespace App\Filament\Resources\ReceivingSessionResource\Pages;

use App\Filament\Resources\ReceivingSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceivingSessions extends ListRecords
{
    protected static string $resource = ReceivingSessionResource::class;

    public function getSubheading(): ?string
    {
        return 'A record of each barcode-scan session used while receiving a pallet.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Receiving Session'),
        ];
    }
}

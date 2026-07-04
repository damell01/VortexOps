<?php

namespace App\Filament\Resources\ReceivingSessionResource\Pages;

use App\Filament\Resources\ReceivingSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceivingSessions extends ListRecords
{
    protected static string $resource = ReceivingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Receiving Session'),
        ];
    }
}

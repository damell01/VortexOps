<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use Filament\Resources\Pages\EditRecord;

class EditStreamerLogEntry extends EditRecord
{
    protected static string $resource = StreamerLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

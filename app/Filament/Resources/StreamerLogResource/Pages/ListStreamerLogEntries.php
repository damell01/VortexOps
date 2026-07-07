<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use Filament\Resources\Pages\ListRecords;

class ListStreamerLogEntries extends ListRecords
{
    protected static string $resource = StreamerLogResource::class;
}

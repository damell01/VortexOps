<?php

namespace App\Filament\Resources\WhatnotChannelResource\Pages;

use App\Filament\Resources\WhatnotChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatnotChannels extends ListRecords
{
    protected static string $resource = WhatnotChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

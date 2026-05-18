<?php

namespace App\Filament\Resources\WhatnotShowResource\Pages;

use App\Filament\Resources\WhatnotShowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatnotShows extends ListRecords
{
    protected static string $resource = WhatnotShowResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

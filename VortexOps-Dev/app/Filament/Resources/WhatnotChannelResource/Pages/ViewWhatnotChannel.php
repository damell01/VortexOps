<?php

namespace App\Filament\Resources\WhatnotChannelResource\Pages;

use App\Filament\Resources\WhatnotChannelResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWhatnotChannel extends ViewRecord
{
    protected static string $resource = WhatnotChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}

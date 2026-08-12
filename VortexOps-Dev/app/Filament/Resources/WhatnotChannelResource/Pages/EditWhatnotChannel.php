<?php

namespace App\Filament\Resources\WhatnotChannelResource\Pages;

use App\Filament\Resources\WhatnotChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatnotChannel extends EditRecord
{
    protected static string $resource = WhatnotChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

<?php

namespace App\Filament\Resources\WhatnotShowResource\Pages;

use App\Filament\Resources\WhatnotShowResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatnotShow extends EditRecord
{
    protected static string $resource = WhatnotShowResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

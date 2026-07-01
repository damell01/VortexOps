<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPallet extends EditRecord
{
    protected static string $resource = PalletResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

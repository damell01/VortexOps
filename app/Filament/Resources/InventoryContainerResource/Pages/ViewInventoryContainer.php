<?php

namespace App\Filament\Resources\InventoryContainerResource\Pages;

use App\Filament\Resources\InventoryContainerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryContainer extends ViewRecord
{
    protected static string $resource = InventoryContainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

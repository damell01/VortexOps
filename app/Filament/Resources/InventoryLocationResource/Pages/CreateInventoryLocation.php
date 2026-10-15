<?php

namespace App\Filament\Resources\InventoryLocationResource\Pages;

use App\Filament\Resources\InventoryLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryLocation extends CreateRecord
{
    protected static string $resource = InventoryLocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return InventoryLocationResource::applyAudience($data);
    }
}

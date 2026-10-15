<?php

namespace App\Filament\Resources\InventoryLocationResource\Pages;

use App\Filament\Resources\InventoryLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryLocation extends EditRecord
{
    protected static string $resource = InventoryLocationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return InventoryLocationResource::applyAudience($data);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

<?php

namespace App\Filament\Resources\InventoryLocationResource\Pages;

use App\Filament\Resources\InventoryLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryLocation extends CreateRecord
{
    protected static string $resource = InventoryLocationResource::class;

    /** @var list<int> businesses the new location is shared by */
    protected array $sharedBy = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        ['data' => $data, 'business_ids' => $this->sharedBy] = InventoryLocationResource::applyAudience($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->businesses()->sync($this->sharedBy);
    }
}

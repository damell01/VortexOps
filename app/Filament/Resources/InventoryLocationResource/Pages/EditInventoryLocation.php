<?php

namespace App\Filament\Resources\InventoryLocationResource\Pages;

use App\Filament\Resources\InventoryLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryLocation extends EditRecord
{
    protected static string $resource = InventoryLocationResource::class;

    /** @var list<int> businesses the location is shared by */
    protected array $sharedBy = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        ['data' => $data, 'business_ids' => $this->sharedBy] = InventoryLocationResource::applyAudience($data);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->businesses()->sync($this->sharedBy);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

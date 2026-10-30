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
    protected array $hiddenAdmins = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->hiddenAdmins = (array) ($data['hidden_admin_user_ids'] ?? []);
        ['data' => $data, 'business_ids' => $this->sharedBy] = InventoryLocationResource::applyAudience($data);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->businesses()->sync($this->sharedBy);
        \App\Models\Setting::set('inventory_hidden_admins:' . $this->record->id, json_encode(array_values(array_map('intval', $this->hiddenAdmins))));
        \App\Support\InventoryVisibility::flushMemo();
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

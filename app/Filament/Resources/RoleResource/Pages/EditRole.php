<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Support\NavVisibility;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['hidden_pages']   = NavVisibility::hiddenForRole($this->record->name);
        $data['readonly_pages'] = NavVisibility::readonlyForRole($this->record->name);

        return $data;
    }

    protected function afterSave(): void
    {
        NavVisibility::setHiddenForRole($this->record->name, $this->data['hidden_pages'] ?? []);
        NavVisibility::setReadonlyForRole($this->record->name, $this->data['readonly_pages'] ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! in_array($this->record->name, ['admin', 'super_admin', 'streamer'])),
        ];
    }
}

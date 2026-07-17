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
        $data['page_perms'] = RoleResource::pagePermsFormState(
            NavVisibility::hiddenForRole($this->record->name),
            NavVisibility::readonlyForRole($this->record->name),
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Same as CreateRole::mutateFormDataBeforeCreate() — page_perms is
        // virtual, persisted via NavVisibility in afterSave(), and must never
        // reach Model::update() on Role's wide-open mass assignment.
        unset($data['page_perms']);

        return $data;
    }

    protected function afterSave(): void
    {
        [$hidden, $readonly] = RoleResource::pagePermsToLists($this->data['page_perms'] ?? []);

        NavVisibility::setHiddenForRole($this->record->name, $hidden);
        NavVisibility::setReadonlyForRole($this->record->name, $readonly);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => ! RoleResource::isCoreRole($this->record->name)),
        ];
    }
}

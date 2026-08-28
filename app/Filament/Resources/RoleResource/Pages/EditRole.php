<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Support\NavVisibility;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected string $view = 'filament.resources.role-resource.pages.edit-role';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['page_perms'] = RoleResource::pagePermsFormState(
            $this->record->name,
            NavVisibility::readonlyForRole($this->record->name),
        );

        return $data;
    }

    public function accessSummary(): array
    {
        $state = $this->data['page_perms'] ?? RoleResource::pagePermsFormState(
            $this->record->name,
            NavVisibility::readonlyForRole($this->record->name),
        );
        $manage = $view = $hidden = 0;

        foreach ($state as $entry) {
            if (! ($entry['visible'] ?? true)) {
                $hidden++;
            } elseif (! ($entry['editable'] ?? true)) {
                $view++;
            } else {
                $manage++;
            }
        }

        return compact('manage', 'view', 'hidden');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['page_perms']);
        return $data;
    }

    protected function afterSave(): void
    {
        [$hidden, $readonly, $visible] = RoleResource::pagePermsToLists($this->data['page_perms'] ?? []);
        NavVisibility::setVisibleForRole($this->record->name, $visible);
        NavVisibility::setHiddenForRole($this->record->name, $hidden);
        NavVisibility::setReadonlyForRole($this->record->name, $readonly);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => ! RoleResource::isCoreRole($this->record->name)),
        ];
    }
}

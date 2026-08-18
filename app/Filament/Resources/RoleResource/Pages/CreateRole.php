<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Support\NavVisibility;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // page_perms is virtual — persisted via NavVisibility in afterCreate(),
        // never a column on the roles table. Role's mass-assignment is wide
        // open (Spatie's model has no $fillable/$guarded restriction), so an
        // unstripped nested array here would otherwise hit the DB as a raw
        // "no such column" insert failure.
        unset($data['page_perms']);

        return $data;
    }

    protected function afterCreate(): void
    {
        [$hidden, $readonly, $visible] = RoleResource::pagePermsToLists($this->data['page_perms'] ?? []);

        // The visible list is what governs; the hidden list is still written so
        // anything reading it directly keeps working.
        NavVisibility::setVisibleForRole($this->record->name, $visible);
        NavVisibility::setHiddenForRole($this->record->name, $hidden);
        NavVisibility::setReadonlyForRole($this->record->name, $readonly);
    }
}

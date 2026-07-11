<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        // Defense in depth: a non-owner can never create a user with a privileged
        // role, even via a crafted request that bypasses the restricted options.
        if (auth()->user()?->isOwner()) {
            return;
        }

        foreach (UserResource::PRIVILEGED_ROLES as $role) {
            if ($this->record->hasRole($role)) {
                $this->record->removeRole($role);
            }
        }
    }
}

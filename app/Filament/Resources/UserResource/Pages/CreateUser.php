<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\UserTeamProfileService;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        // Nobody — owner included — may mint a new super admin, even via a
        // crafted request that bypasses the restricted select options.
        foreach (UserResource::UNGRANTABLE_ROLES as $role) {
            if ($this->record->hasRole($role)) {
                $this->record->removeRole($role);
            }
        }

        // Defense in depth: a non-owner can never create a user with a privileged
        // role, even via a crafted request that bypasses the restricted options.
        if (! auth()->user()?->isOwner()) {
            foreach (UserResource::PRIVILEGED_ROLES as $role) {
                if ($this->record->hasRole($role)) {
                    $this->record->removeRole($role);
                }
            }
        }

        // Users is the single setup flow for operational staff. Streamer and
        // fulfillment roles automatically receive the backing team profile used
        // by shows, payroll, inventory and fulfillment.
        app(UserTeamProfileService::class)->sync(
            $this->record,
            $this->data['streamer_type'] ?? 'in_house',
        );
    }
}

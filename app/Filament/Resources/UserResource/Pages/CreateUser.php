<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Streamer;
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

        // A streamer login and streamer profile are one concept operationally.
        // Creating the user with the streamer role should be the only setup step.
        if ($this->record->hasRole('streamer') && ! $this->record->streamer) {
            Streamer::create([
                'user_id' => $this->record->id,
                'name' => $this->record->name,
                'legal_name' => $this->record->name,
                'email' => $this->record->email,
                'status' => 'active',
                'member_type' => 'streamer',
            ]);
        }
    }
}

<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\UserTeamProfileService;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** Whether the admin typed a temporary password (it must then be changed at first sign-in). */
    private bool $temporaryPassword = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->temporaryPassword = filled($data['password'] ?? null);
        if (! $this->temporaryPassword) {
            // Nobody knows this one: they set their own from the invite.
            $data['password'] = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::password(40));
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->forceFill(['must_change_password' => $this->temporaryPassword])->save();
        if ($this->data['send_invite'] ?? true) {
            UserResource::notifyInviteResult(\App\Support\AccountInvite::send($this->record, auth()->user()), $this->record);
        } elseif (! $this->temporaryPassword) {
            \Filament\Notifications\Notification::make()->title('No password and no invite')
                ->body('Send an invite from the user\'s page so they can set a password.')->warning()->persistent()->send();
        }

        // Nobody — owner included — may mint a new super admin, even via a
        // crafted request that bypasses the restricted select options.
        foreach (auth()->user()?->isOwner() ? UserResource::UNGRANTABLE_ROLES : UserResource::PRIVILEGED_ROLES as $role) {
            if ($this->record->hasRole($role)) {
                $this->record->removeRole($role);
            }
        }

        // Admin and Fulfillment Admin are intentionally assignable from Users.
        // super_admin remains blocked above.

        // Users is the single setup flow for operational staff. Streamer and
        // fulfillment roles automatically receive the backing team profile used
        // by shows, payroll, inventory and fulfillment.
        $profile = app(UserTeamProfileService::class)->sync(
            $this->record,
            $this->data['streamer_type'] ?? 'in_house',
            $this->data['streamer_aliases'] ?? [],
        );

        // The Pay section: blank boxes follow the team default.
        if ($profile) {
            \App\Support\UserPayFields::apply($profile, $this->data);
        }
    }
}

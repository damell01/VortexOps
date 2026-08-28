<?php

namespace App\Filament\Resources\StreamerResource\Pages;

use App\Filament\Resources\StreamerResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateStreamer extends CreateRecord
{
    protected static string $resource = StreamerResource::class;

    /** @var array{email: string, password: string}|null */
    private ?array $loginData = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['create_login']) && ! empty($data['login_email']) && ! empty($data['login_password'])) {
            $this->loginData = [
                'email'    => $data['login_email'],
                'password' => $data['login_password'],
            ];
        }

        unset($data['create_login'], $data['login_email'], $data['login_password']);

        // New people inherit their role Payment Structure. Existing rows stay
        // legacy-safe until an admin deliberately opts them into inheritance.
        $data['compensation_override_fields'] = [];

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->loginData) {
            return;
        }

        $user = User::create([
            'name'     => $this->record->name,
            'email'    => $this->loginData['email'],
            'password' => Hash::make($this->loginData['password']),
        ]);

        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $user->assignRole('streamer');

        $this->record->update(['user_id' => $user->id]);

        Notification::make()
            ->title("Login created — {$this->loginData['email']} can now sign in as {$this->record->name}.")
            ->success()
            ->send();
    }
}

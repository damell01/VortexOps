<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\StreamerShows;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;

class Login extends BaseLogin
{
    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Remember me for 30 days');
    }

    protected function getRedirectUrl(): string
    {
        $user = auth()->user();

        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            return StreamerShows::getUrl();
        }

        return parent::getRedirectUrl();
    }
}

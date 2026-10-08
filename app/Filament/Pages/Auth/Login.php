<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\StreamerShows;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Component;

class Login extends BaseLogin
{
    public function getHeading(): string
    {
        return 'Welcome back';
    }

    public function getSubheading(): ?string
    {
        return 'Sign in to Vortex Ops to manage shows, inventory, fulfillment, and payroll.';
    }
    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Remember me for 30 days');
    }

    protected function getRedirectUrl(): string
    {
        $user = auth()->user();

        if ($user?->must_change_password) return route('account.password.edit');

        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            return StreamerShows::getUrl();
        }

        return parent::getRedirectUrl();
    }
}

<?php

namespace App\Filament\Pages\Auth;

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
}

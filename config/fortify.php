<?php

use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    // Redirect to Filament dashboard after any successful auth action
    'home' => '/admin',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login'      => 'login',
        'two-factor' => 'two-factor',
    ],

    // Disable Fortify views — Filament owns login/logout/password-reset UI.
    // We only need the 2FA challenge view from Fortify.
    'views' => false,

    'features' => [
        // Registration/login/password-reset handled by Filament — disabled here.
        // Features::registration(),
        // Features::resetPasswords(),
        // Features::updateProfileInformation(),
        // Features::updatePasswords(),

        Features::twoFactorAuthentication([
            'confirm'         => true,
            'confirmPassword' => true,
        ]),
    ],

];

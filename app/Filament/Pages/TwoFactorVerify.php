<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

class TwoFactorVerify extends Page
{
    protected static bool   $shouldRegisterNavigation = false;
    protected static ?string $slug  = 'two-factor-verify';
    protected static ?string $title = 'Two-Factor Authentication';

    public string $code          = '';
    public string $recoveryCode  = '';
    public bool   $usingRecovery = false;

    public function getView(): string
    {
        return 'filament.pages.two-factor-verify';
    }

    public function mount(): void
    {
        // If they don't have 2FA enabled or already verified, skip
        $user = Auth::user();
        if (! $user || ! $user->two_factor_confirmed_at || session('vx_2fa_verified')) {
            $this->redirect(route('filament.admin.pages.dashboard-improved'));
        }
    }

    public function verify(): void
    {
        $user = Auth::user();

        if ($this->usingRecovery) {
            $this->verifyRecoveryCode($user);
        } else {
            $this->verifyTotp($user);
        }
    }

    private function verifyTotp($user): void
    {
        $valid = app(\PragmaRX\Google2FA\Google2FA::class)
            ->verifyKey(decrypt($user->two_factor_secret), $this->code);

        if ($valid) {
            $this->markVerified();
        } else {
            Notification::make()->title('Invalid code. Please try again.')->danger()->send();
            $this->code = '';
        }
    }

    private function verifyRecoveryCode($user): void
    {
        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        $input = $this->recoveryCode;

        $matched = collect($codes)->first(fn ($c) => hash_equals($c, $input));

        if ($matched) {
            // Burn the used code
            $remaining = collect($codes)->reject(fn ($c) => $c === $matched)->values()->all();
            $user->forceFill(['two_factor_recovery_codes' => encrypt(json_encode($remaining))])->save();
            $this->markVerified();
        } else {
            Notification::make()->title('Invalid recovery code.')->danger()->send();
            $this->recoveryCode = '';
        }
    }

    private function markVerified(): void
    {
        session(['vx_2fa_verified' => true]);
        $this->redirect(route('filament.admin.pages.dashboard-improved'));
    }
}

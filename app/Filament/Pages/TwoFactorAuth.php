<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class TwoFactorAuth extends Page
{
    protected static ?string $title          = 'Two-Factor Authentication';
    protected static ?string $slug           = 'two-factor';
    protected static ?int    $navigationSort = 90;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return null;
    }

    public static function getNavigationLabel(): string
    {
        return '2FA Security';
    }

    public string $confirmationCode = '';
    public bool   $showingQr        = false;
    public bool   $showingRecovery  = false;

    public function getView(): string
    {
        return 'filament.pages.two-factor-auth';
    }

    public function getUser(): \App\Models\User
    {
        return Auth::user();
    }

    public function isEnabled(): bool
    {
        return ! empty($this->getUser()->two_factor_secret);
    }

    public function isConfirmed(): bool
    {
        return ! empty($this->getUser()->two_factor_confirmed_at);
    }

    // ── Enable ────────────────────────────────────────────────────────────────

    public function enable(): void
    {
        app(EnableTwoFactorAuthentication::class)($this->getUser());
        $this->showingQr       = true;
        $this->showingRecovery = true;

        Notification::make()
            ->title('Scan the QR code with your authenticator app, then confirm below.')
            ->success()
            ->send();
    }

    // ── Confirm ───────────────────────────────────────────────────────────────

    public function confirm(): void
    {
        try {
            app(ConfirmTwoFactorAuthentication::class)($this->getUser(), $this->confirmationCode);
            $this->confirmationCode = '';
            Notification::make()->title('Two-factor authentication confirmed.')->success()->send();
        } catch (\Throwable) {
            Notification::make()->title('The code was invalid. Please try again.')->danger()->send();
        }
    }

    // ── Regenerate recovery codes ─────────────────────────────────────────────

    public function regenerateCodes(): void
    {
        app(GenerateNewRecoveryCodes::class)($this->getUser());
        $this->showingRecovery = true;
        Notification::make()->title('Recovery codes regenerated. Save them somewhere safe.')->warning()->send();
    }

    // ── Disable ───────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        return [
            Action::make('disable')
                ->label('Disable 2FA')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('Disable two-factor authentication?')
                ->modalDescription('This will remove the 2FA requirement from your account.')
                ->action(function () {
                    app(DisableTwoFactorAuthentication::class)($this->getUser());
                    $this->showingQr       = false;
                    $this->showingRecovery = false;
                    Notification::make()->title('Two-factor authentication disabled.')->warning()->send();
                }),
        ];
    }
}

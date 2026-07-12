<?php

namespace App\Filament\Pages;

use Database\Seeders\DemoDataSeeder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

/**
 * A one-click way to load a full set of demo data so every flow in the app can
 * be exercised on a fresh deployment — shows with sold items, the streamer log
 * review/approval pipeline, mapped and unmapped inventory, a scannable pallet,
 * payouts, a loan, and feedback tickets. Owner-only; the seeder is idempotent,
 * so the button is safe to press repeatedly.
 */
class DemoData extends Page
{
    protected static ?string $title = 'Demo Data';
    protected static ?string $navigationLabel = 'Demo Data';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    // Owner or super_admin only — a testing tool that seeds data, so regular
    // admins are excluded (it must never be used to seed a production DB).
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->isOwner() || $user?->hasRole('super_admin')) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getView(): string
    {
        return 'filament.pages.demo-data';
    }

    public function getSubheading(): ?string
    {
        return 'Load a complete sample dataset so you can try every flow end to end.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('load')
                ->label('Load / refresh demo data')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Load demo data')
                ->modalDescription('Seeds sample streamers, shows, sold items, inventory, a scannable pallet, payouts, a loan, and feedback. It only adds what is missing, so existing records are left untouched.')
                ->modalSubmitActionLabel('Load it')
                ->action(function (): void {
                    if (! static::canAccess()) {
                        Notification::make()->title('You do not have permission to load demo data')->danger()->send();

                        return;
                    }

                    try {
                        Artisan::call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Demo data failed to load')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Demo data loaded')
                        ->body('Sample data is ready across every module. Streamer logins use the password "demopassword".')
                        ->success()
                        ->send();
                }),
        ];
    }
}

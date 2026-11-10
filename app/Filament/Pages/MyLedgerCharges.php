<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Services\LedgerChargeReport;
use Filament\Pages\Page;

/**
 * A charged streamer's own ledger charges: what Whatnot charged back on their
 * shows (shipping charges, refunds, order refund shipping charges), so they
 * know what they are charged for.
 */
class MyLedgerCharges extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'payouts';
    protected static ?string $title = 'My charges';
    protected static ?string $slug = 'my-charges';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?int $navigationSort = 92;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return \App\Support\AdminModules::navigationGroupFor('payouts');
    }

    protected static function passesModuleAccessCheck(): bool
    {
        return auth()->check();
    }

    public static function canAccess(): bool
    {
        $streamer = auth()->user()?->streamer;

        return LedgerChargeReport::isCharged($streamer);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getView(): string
    {
        return 'filament.pages.my-ledger-charges';
    }

    public function getSubheading(): ?string
    {
        return 'Shipping charges, refunds and order refund shipping charges on your shows.';
    }

    public function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        return ['streamerId' => (int) auth()->user()->streamer->id];
    }
}

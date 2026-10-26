<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Support\AssistantPaidPay;
use Filament\Pages\Page;
use Livewire\WithPagination;

class MyPay extends Page
{
    use HasModuleAccess, WithPagination;

    protected static string $moduleSlug = 'payouts';
    protected static ?string $title = 'My Pay';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return \App\Support\AdminModules::navigationGroupFor('payouts');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStreamerAssistant()
            && \App\Support\AdminModules::isEnabled('payouts')
            && ! \App\Support\NavVisibility::isHiddenForUser(static::class, auth()->user());
    }

    public function getView(): string { return 'filament.pages.my-pay'; }

    public function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $pay = AssistantPaidPay::query(auth()->user());
        return [
            'paidTotal' => (clone $pay)->sum('calculated_payout'),
            'payments' => $pay->with(['batch', 'show'])->latest('id')->paginate(15),
        ];
    }
}

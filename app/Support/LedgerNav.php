<?php

namespace App\Support;

use App\Filament\Pages\PayChangedSincePaid;
use App\Filament\Resources\WhatnotLedgerResource;

/**
 * The ledger and the money checks built on it, as one menu section: the
 * ledger itself, its charges and tip reports, and the alerts that need a
 * person to look (pay changed after payout, tips waiting on a business).
 */
final class LedgerNav
{
    /** @return list<array{0:string,1:string,2:string,3:string}> */
    public static function links(): array
    {
        if (! WhatnotLedgerResource::canAccess()) return [];
        $section = 'Ledger & checks';

        return array_values(array_filter([
            ['Ledger', WhatnotLedgerResource::getUrl('index'), $section, 'heroicon-o-document-text'],
            ['Ledger Charges', WhatnotLedgerResource::getUrl('charges'), $section, 'heroicon-o-receipt-refund'],
            ['Streamer Tip Totals', WhatnotLedgerResource::getUrl('tips'), $section, 'heroicon-o-gift'],
            ['Tips Needing a Business', WhatnotLedgerResource::getUrl('index', ['review' => 'tips_needs_business', 'period' => 'all']), $section, 'heroicon-o-exclamation-triangle'],
            PayChangedSincePaid::canAccess() ? [self::changedLabel(), PayChangedSincePaid::getUrl(panel: 'admin'), $section, 'heroicon-o-bell-alert'] : null,
        ]));
    }

    /** "Changed After Payout", with the last known count when there is one. */
    public static function changedLabel(): string
    {
        $count = app(\App\Services\PayChangedAfterPayout::class)->count();

        return 'Changed After Payout' . ($count ? " ({$count})" : '');
    }
}

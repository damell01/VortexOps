<?php

namespace App\Support;

use App\Filament\Pages;
use App\Filament\Resources\WhatnotLedgerResource;

/**
 * Every report, grouped, for the report library beside each report. Only
 * the ones the signed-in person can open are listed; the order here is the
 * order on screen.
 */
final class ReportCatalog
{
    /** @return list<array{group:string, items:list<array{label:string, icon:string, url:string, class:string}>}> */
    private static ?array $memo = null;

    public static function forUser(): array
    {
        if (self::$memo !== null && ! app()->runningUnitTests()) return self::$memo;

        $groups = [
            'Overview' => [
                [Pages\ReportsOverview::class, 'Executive overview', 'heroicon-o-squares-2x2'],
                [Pages\ReportCompare::class, 'Compare', 'heroicon-o-arrows-right-left'],
                [Pages\Goals::class, 'Goals', 'heroicon-o-flag'],
            ],
            'Sales' => [
                [Pages\Reports::class, 'Whatnot Performance', 'heroicon-o-chart-bar'],
                [Pages\ShowPerformance::class, 'Show Performance', 'heroicon-o-clock'],
                [Pages\ShowFormatComparison::class, 'Show Formats', 'heroicon-o-squares-plus'],
                [Pages\GiveawayRoiReport::class, 'Giveaway ROI', 'heroicon-o-gift'],
                [Pages\WhatnotLosses::class, 'Whatnot Losses', 'heroicon-o-arrow-trending-down'],
            ],
            'Money' => [
                [Pages\ProfitLoss::class, 'Business Profitability', 'heroicon-o-currency-dollar'],
                [Pages\StreamerAnalytics::class, 'Streamer Analytics', 'heroicon-o-user-group'],
                [Pages\StreamerStatement::class, 'Streamer Statement', 'heroicon-o-document-text'],
                [WhatnotLedgerResource::class, 'Ledger', 'heroicon-o-book-open'],
            ],
            'Inventory' => [
                [Pages\InventoryReport::class, 'Inventory Report', 'heroicon-o-cube'],
                [Pages\ProductInsights::class, 'Product Insights', 'heroicon-o-light-bulb'],
                [Pages\ReceivingAnalytics::class, 'Receiving', 'heroicon-o-inbox-arrow-down'],
            ],
        ];

        $out = [];
        foreach ($groups as $group => $items) {
            $list = [];
            foreach ($items as [$class, $label, $icon]) {
                try {
                    if (! class_exists($class) || ! $class::canAccess()) continue;
                    $url = is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::getUrl('index') : $class::getUrl();
                } catch (\Throwable) {
                    continue;
                }
                $list[] = ['label' => $label, 'icon' => $icon, 'url' => $url, 'class' => $class];
            }
            // The ledger's own reports and checks sit under Money too.
            if ($group === 'Money') {
                foreach (LedgerNav::links() as [$label, $url, , $icon]) {
                    if ($label === 'Ledger') continue;
                    $list[] = ['label' => $label, 'icon' => $icon, 'url' => $url, 'class' => null];
                }
            }
            if ($list) $out[] = ['group' => $group, 'items' => $list];
        }

        return self::$memo = $out;
    }
}

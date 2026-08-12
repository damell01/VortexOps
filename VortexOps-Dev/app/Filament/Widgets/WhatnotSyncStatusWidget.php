<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Order-data freshness for the Fulfillment Center — reads the same
 * whatnot_last_import_success_at heartbeat SystemHealth/NeedsAttentionWidget
 * already track (imports run every 15 min), so fulfillment workers can see
 * at a glance whether the show/order list they're looking at is current.
 *
 * Deliberately scoped to order data, not shipping status: shipping_status
 * and tracking_number are set manually here today, not pulled from Whatnot.
 */
class WhatnotSyncStatusWidget extends BaseWidget
{
    // A single Setting::get() read is cheap enough to render synchronously —
    // no reason to show a loading skeleton first for this one.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $last = Setting::get('whatnot_last_import_success_at');

        if (! $last) {
            return [
                Stat::make('Whatnot Order Data', 'No sync recorded yet')
                    ->description('Run php artisan whatnot:import, or check the scheduler')
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color('gray'),
            ];
        }

        $syncedAt = Carbon::parse($last);
        $stale = $syncedAt->diffInMinutes(now()) >= 30;

        return [
            Stat::make('Whatnot Order Data', 'Synced ' . $syncedAt->diffForHumans())
                ->description($stale ? 'Running later than usual — check the Sync Dashboard' : 'Up to date')
                ->descriptionIcon($stale ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($stale ? 'warning' : 'success'),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\WhatnotDailyOverview;
use RuntimeException;

/**
 * analytics_overview_report CSV (one row per day, developers.whatnot.com/reports)
 * into whatnot_daily_overviews. Re-importing a day replaces it: Whatnot's
 * numbers for a day can settle after the day ends.
 */
final class WhatnotApiOverviewImporter
{
    public const MONEY = ['estimated_sales', 'in_show_estimated_sales', 'marketplace_estimated_sales',
        'estimated_earnings', 'in_show_estimated_earnings', 'marketplace_estimated_earnings', 'average_order_value'];

    public const COUNTS = ['orders_count', 'in_show_orders_count', 'marketplace_orders_count', 'giveaway_orders_count',
        'buyers_count', 'new_buyers_count', 'returning_buyers_count', 'follows_count', 'referral_follows_count',
        'non_referral_follows_count', 'livestreams_count', 'stream_minutes', 'viewers_per_hour', 'average_watch_time_seconds'];

    /** @return array{received:int, saved:int, invalid:int} */
    public function import(int $channelId, string $csv, bool $dryRun = true): array
    {
        if (! $dryRun && ! WhatnotApiOrderApplier::writesAllowed()) {
            throw new RuntimeException('Applying API data is disabled.');
        }
        $rows = WhatnotApiCsv::rows($csv, ['date']);
        $counts = ['received' => count($rows), 'saved' => 0, 'invalid' => 0];
        foreach ($rows as $row) {
            $date = $row['date'] ?? null;
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                $counts['invalid']++;
                continue;
            }
            $values = ['currency' => $row['currency'] ?? null];
            foreach (self::MONEY as $field) $values[$field] = is_numeric($row[$field] ?? null) ? round((float) $row[$field], 2) : null;
            foreach (self::COUNTS as $field) $values[$field] = is_numeric($row[$field] ?? null) ? (int) $row[$field] : null;
            $counts['saved']++;
            if (! $dryRun) {
                WhatnotDailyOverview::updateOrCreate(['whatnot_channel_id' => $channelId, 'date' => substr($date, 0, 10)], $values);
            }
        }

        return $counts;
    }
}

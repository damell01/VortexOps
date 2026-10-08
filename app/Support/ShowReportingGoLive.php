<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * The date show reports start being required.
 *
 * Shows before it were reported the old way, so nothing should ask a
 * streamer to file a report for them: they are left out of "waiting on you",
 * the nav badge, the End of Stream picker and every "needs a report" count.
 * The shows themselves are untouched and still appear on the calendar.
 *
 * Stored as Y-m-d under show_reports_required_from; blank means no cut-off.
 */
class ShowReportingGoLive
{
    public const SETTING = 'show_reports_required_from';

    /** Not memoised: Setting::get is cached already, and a static memo would go stale in queue workers. */
    public static function date(): ?Carbon
    {
        try {
            $raw = trim((string) Setting::get(self::SETTING, ''));
            return $raw === '' ? null : Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Whether a show dated $showDate is one a report is expected for. */
    public static function covers($showDate): bool
    {
        $from = static::date();

        if (! $from || ! $showDate) {
            return true;
        }

        return Carbon::parse($showDate)->startOfDay()->greaterThanOrEqualTo($from);
    }

    /**
     * Narrow a shows query to those a report is expected for.
     *
     * @template T of QueryBuilder
     * @param  T  $query
     * @return T
     */
    public static function scope($query, string $column = 'show_date')
    {
        if ($from = static::date()) {
            $query->whereDate($column, '>=', $from->toDateString());
        }

        return $query;
    }
}

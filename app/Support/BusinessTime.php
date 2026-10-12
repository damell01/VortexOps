<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The team's local time.
 *
 * The app stores timestamps in UTC, but the business runs on Central time
 * (config app.display_timezone). A show's date and start time are local
 * wall-clock values: a 9pm show on Oct 12 is Oct 12, even though it is
 * already Oct 13 in UTC.
 */
class BusinessTime
{
    public static function zone(): string
    {
        return (string) (config('app.display_timezone') ?: config('app.timezone', 'UTC'));
    }

    /** A moment in the team's local time. */
    public static function local(CarbonInterface|string|null $moment = null): Carbon
    {
        return Carbon::parse($moment ?? now())->setTimezone(self::zone());
    }

    /**
     * A time from Whatnot, in local time. Epoch numbers and ISO strings with
     * a "Z" or an offset are exact moments and are converted; a plain
     * "2026-10-12 21:00" is already local and is read as such.
     */
    public static function fromWhatnot(mixed $raw): Carbon
    {
        if (is_int($raw) || is_float($raw) || preg_match('/^\d{9,14}(\.\d+)?$/', trim((string) $raw))) {
            $n = (float) $raw;

            return Carbon::createFromTimestampMs($n < 1e11 ? $n * 1000 : $n, 'UTC')->setTimezone(self::zone());
        }

        $text = trim((string) $raw);
        if (preg_match('/(Z|[+-]\d{2}:?\d{2}|\bUTC|\bGMT)$/i', $text)) {
            return Carbon::parse($text)->setTimezone(self::zone());
        }

        return Carbon::parse($text, self::zone());
    }
}

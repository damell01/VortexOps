<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\WeeklyPayoutBatch;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Which seven days make up a pay week.
 *
 * The start day is a setting (Rates & Adjustments → Pay schedule), Monday by
 * default. Pay Runs already created keep their dates. When the start day
 * changes, the first new period begins the day after the last existing run
 * ends, so no day is skipped and no two runs overlap; from then on periods
 * are the regular seven days.
 */
class PaySchedule
{
    public const SETTING = 'payroll_week_start_day';

    /** @return array<int, string> Carbon day number => name */
    public static function dayOptions(): array
    {
        return [
            CarbonInterface::MONDAY => 'Monday',
            CarbonInterface::TUESDAY => 'Tuesday',
            CarbonInterface::WEDNESDAY => 'Wednesday',
            CarbonInterface::THURSDAY => 'Thursday',
            CarbonInterface::FRIDAY => 'Friday',
            CarbonInterface::SATURDAY => 'Saturday',
            CarbonInterface::SUNDAY => 'Sunday',
        ];
    }

    public static function startDay(): int
    {
        $day = Setting::get(self::SETTING);

        return is_numeric($day) && array_key_exists((int) $day, self::dayOptions())
            ? (int) $day
            : CarbonInterface::MONDAY;
    }

    public static function startDayName(): string
    {
        return self::dayOptions()[self::startDay()];
    }

    public static function endDayName(): string
    {
        return self::dayOptions()[(self::startDay() + 6) % 7];
    }

    /** The regular seven-day period containing $date, ignoring existing runs. */
    public static function standardPeriod(CarbonInterface|string $date): array
    {
        $start = Carbon::parse($date)->startOfDay()->startOfWeek(self::startDay());

        return [$start, $start->copy()->addDays(6)];
    }

    /**
     * The pay period containing $date: an existing Pay Run's dates when one
     * covers it, otherwise the regular period trimmed so it does not overlap
     * a neighbouring run.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodFor(CarbonInterface|string $date): array
    {
        $date = Carbon::parse($date)->startOfDay();

        $covering = WeeklyPayoutBatch::query()
            ->whereDate('week_start', '<=', $date->toDateString())
            ->whereDate('week_end', '>=', $date->toDateString())
            ->orderBy('week_start')
            ->first();
        if ($covering) {
            return [$covering->week_start->copy()->startOfDay(), $covering->week_end->copy()->startOfDay()];
        }

        [$start, $end] = self::standardPeriod($date);

        $before = WeeklyPayoutBatch::query()
            ->whereDate('week_end', '>=', $start->toDateString())
            ->whereDate('week_end', '<', $date->toDateString())
            ->orderByDesc('week_end')
            ->first();
        if ($before) {
            $start = $before->week_end->copy()->startOfDay()->addDay();
        }

        $after = WeeklyPayoutBatch::query()
            ->whereDate('week_start', '>', $date->toDateString())
            ->whereDate('week_start', '<=', $end->toDateString())
            ->orderBy('week_start')
            ->first();
        if ($after) {
            $end = $after->week_start->copy()->startOfDay()->subDay();
        }

        return [$start, $end];
    }

    /**
     * Consecutive pay periods covering $from to $to.
     *
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    public static function periodsBetween(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $cursor = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $periods = [];

        while ($cursor->lte($to) && count($periods) < 520) {
            $period = self::periodFor($cursor);
            $periods[] = $period;
            $cursor = $period[1]->copy()->addDay();
        }

        return $periods;
    }

    public static function label(CarbonInterface $start, CarbonInterface $end): string
    {
        return $start->format('D M j').' – '.$end->format('D M j, Y');
    }
}

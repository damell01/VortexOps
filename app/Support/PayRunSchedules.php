<?php

namespace App\Support;

use App\Models\Payout;
use App\Models\Setting;
use App\Models\WeeklyPayoutBatch;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Pay run types and their schedules.
 *
 * Off by default: every run pays everything ("All pay") on the weekly
 * schedule, as it always has. Turned on, profit share is paid on its own
 * runs and everything else — hourly pay, time not on a show, per-package and
 * flat pay — on wage runs, each type with its own frequency, start date and
 * whether runs are created automatically. A run of any type can still be
 * created by hand for any dates.
 */
class PayRunSchedules
{
    public const SETTING = 'pay_run_schedules';

    public const KINDS = [
        'wages' => 'Hourly & wages',
        'share' => 'Profit share',
    ];

    public const FREQUENCIES = [
        'weekly' => 'Every week',
        'biweekly' => 'Every two weeks',
        'semimonthly' => 'Twice a month (1st–15th, 16th–end)',
        'monthly' => 'Every month',
    ];

    /** Pay line types that are paid on profit share runs. */
    public const SHARE_TYPES = ['profit_share', 'sales_split'];

    /** @return array{separate:bool,wages:array{frequency:string,anchor:?string,auto:bool},share:array{frequency:string,anchor:?string,auto:bool}} */
    public static function config(): array
    {
        $raw = json_decode((string) Setting::get(self::SETTING, ''), true);
        $raw = is_array($raw) ? $raw : [];
        $kind = fn (string $k, string $freq) => [
            'frequency' => array_key_exists($raw[$k]['frequency'] ?? '', self::FREQUENCIES) ? $raw[$k]['frequency'] : $freq,
            'anchor' => self::validDate($raw[$k]['anchor'] ?? null),
            'auto' => (bool) ($raw[$k]['auto'] ?? true),
        ];

        return [
            'separate' => (bool) ($raw['separate'] ?? false),
            'wages' => $kind('wages', 'weekly'),
            'share' => $kind('share', 'monthly'),
        ];
    }

    public static function save(array $config): void
    {
        $clean = ['separate' => (bool) ($config['separate'] ?? false)];
        foreach (array_keys(self::KINDS) as $k) {
            $clean[$k] = [
                'frequency' => array_key_exists($config[$k]['frequency'] ?? '', self::FREQUENCIES) ? $config[$k]['frequency'] : ($k === 'share' ? 'monthly' : 'weekly'),
                'anchor' => self::validDate($config[$k]['anchor'] ?? null),
                'auto' => (bool) ($config[$k]['auto'] ?? false),
            ];
        }
        Setting::set(self::SETTING, json_encode($clean));
    }

    public static function separate(): bool
    {
        return self::config()['separate'];
    }

    /** The kinds runs are made for right now: both types when separate, else "All pay" (null). */
    public static function activeKinds(): array
    {
        return self::separate() ? array_keys(self::KINDS) : [null];
    }

    public static function label(?string $kind): string
    {
        return $kind === null ? 'All pay' : (self::KINDS[$kind] ?? $kind);
    }

    /** Which run type pays this line. */
    public static function kindOfType(?string $payoutType): string
    {
        return in_array($payoutType, self::SHARE_TYPES, true) ? 'share' : 'wages';
    }

    public static function kindOf(Payout $payout): string
    {
        return self::kindOfType($payout->payout_type);
    }

    /**
     * The regular period of $kind containing $date, from its frequency and
     * start date, ignoring existing runs.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function standardPeriod(string $kind, CarbonInterface|string $date, ?array $cfg = null): array
    {
        $date = Carbon::parse($date)->startOfDay();
        $cfg = $cfg ?? self::config()[$kind] ?? [];
        $cfg = [
            'frequency' => array_key_exists($cfg['frequency'] ?? '', self::FREQUENCIES) ? $cfg['frequency'] : 'weekly',
            'anchor' => self::validDate($cfg['anchor'] ?? null),
        ];

        switch ($cfg['frequency']) {
            case 'monthly':
                $day = $cfg['anchor'] ? Carbon::parse($cfg['anchor'])->day : 1;
                $day = min($day, 28);
                $start = $date->copy()->day(1)->addDays($day - 1);
                if ($start->gt($date)) {
                    $start = $start->subMonthNoOverflow();
                }

                return [$start, $start->copy()->addMonthNoOverflow()->subDay()];
            case 'semimonthly':
                return $date->day <= 15
                    ? [$date->copy()->day(1), $date->copy()->day(15)]
                    : [$date->copy()->day(16), $date->copy()->endOfMonth()->startOfDay()];
            case 'biweekly':
                // Without a chosen start date, fortnights count from a fixed
                // week so every date lands in the same pair of weeks.
                $anchor = $cfg['anchor'] ? Carbon::parse($cfg['anchor'])->startOfDay() : PaySchedule::standardPeriod('2026-01-05')[0];
                $offset = (int) floor($anchor->diffInDays($date, false) / 14);
                $start = $anchor->copy()->addDays($offset * 14);

                return [$start, $start->copy()->addDays(13)];
            default:
                if ($cfg['anchor']) {
                    $anchor = Carbon::parse($cfg['anchor'])->startOfDay();
                    $start = $anchor->copy()->addDays((int) floor($anchor->diffInDays($date, false) / 7) * 7);

                    return [$start, $start->copy()->addDays(6)];
                }

                return PaySchedule::standardPeriod($date);
        }
    }

    /**
     * The period of $kind containing $date: an existing run of that type
     * when one covers it, else the regular period trimmed so it does not
     * overlap a neighbouring run of the same type.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodFor(?string $kind, CarbonInterface|string $date, ?int $businessId = null): array
    {
        if ($kind === null) {
            return PaySchedule::periodFor($date, $businessId);
        }

        $date = Carbon::parse($date)->startOfDay();
        $runs = fn () => WeeklyPayoutBatch::query()->sharingPeopleWith($businessId)->sharingKindWith($kind);

        $covering = $runs()->whereDate('week_start', '<=', $date->toDateString())->whereDate('week_end', '>=', $date->toDateString())->orderBy('week_start')->first();
        if ($covering) {
            return [$covering->week_start->copy()->startOfDay(), $covering->week_end->copy()->startOfDay()];
        }

        [$start, $end] = self::standardPeriod($kind, $date);
        $before = $runs()->whereDate('week_end', '>=', $start->toDateString())->whereDate('week_end', '<', $date->toDateString())->orderByDesc('week_end')->first();
        if ($before) {
            $start = $before->week_end->copy()->startOfDay()->addDay();
        }
        $after = $runs()->whereDate('week_start', '>', $date->toDateString())->whereDate('week_start', '<=', $end->toDateString())->orderBy('week_start')->first();
        if ($after) {
            $end = $after->week_start->copy()->startOfDay()->subDay();
        }

        return [$start, $end];
    }

    private static function validDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}

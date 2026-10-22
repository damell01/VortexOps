<?php

namespace App\Support;

use App\Models\Payout;
use App\Models\WeeklyPayoutBatch;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/** Period calculation and wage/share coverage for manual and optional scheduled runs. */
class PayRunSchedules
{
    public const KINDS = [
        'wages' => 'Hourly & wages',
        'share' => 'Profit share',
        'tips' => 'Tips',
        'other' => 'Other Payment (one-off)',
    ];

    public const FREQUENCIES = [
        'weekly' => 'Every week',
        'biweekly' => 'Every two weeks',
        'semimonthly' => 'Twice a month (1st–15th, 16th–end)',
        'monthly' => 'Every month',
    ];

    /**
     * Pay line types that are paid on profit share runs. A sales split is an
     * independent streamer's earnings, not a share of profit, so it is paid
     * with wages.
     */
    public const SHARE_TYPES = ['profit_share', 'custom_formula', 'shift_share'];

    /**
     * Pay lines only a Tips run pays: tips a business pays monthly. Every
     * other run, "All pay" included, leaves them for it.
     */
    public const TIP_TYPES = ['show_tips'];

    /** Limit a payout query to the lines a run of $kind pays. */
    public static function scopeToKind(\Illuminate\Database\Eloquent\Builder $query, ?string $kind): \Illuminate\Database\Eloquent\Builder
    {
        return match ($kind) {
            'tips' => $query->whereIn('payout_type', self::TIP_TYPES),
            'other' => $query->where('payout_type', 'other_payment'),
            'share' => $query->whereIn('payout_type', self::SHARE_TYPES),
            'wages' => $query->whereNotIn('payout_type', [...self::SHARE_TYPES, ...self::TIP_TYPES, 'other_payment']),
            default => $query->whereNotIn('payout_type', [...self::TIP_TYPES, 'other_payment']),
        };
    }

    /** Explicit backfill/setup still defaults to a combined run. */
    public static function activeKinds(): array
    {
        return [null];
    }

    public static function label(?string $kind): string
    {
        return $kind === null ? 'All pay at once' : (self::KINDS[$kind] ?? $kind);
    }

    /** Which run type pays this line. */
    public static function kindOfType(?string $payoutType): string
    {
        return match (true) {
            $payoutType === 'other_payment' => 'other',
            in_array($payoutType, self::TIP_TYPES, true) => 'tips',
            in_array($payoutType, self::SHARE_TYPES, true) => 'share',
            default => 'wages',
        };
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
        $cfg = $cfg ?? [];
        $cfg = [
            'frequency' => array_key_exists($cfg['frequency'] ?? '', self::FREQUENCIES) ? $cfg['frequency'] : ($kind === 'tips' ? 'monthly' : 'weekly'),
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

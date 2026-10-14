<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * When a schedulable notification goes out: as each show qualifies
 * (immediate), once a day, or once a week — at a set time, in Central time
 * unless the rule names another zone.
 *
 * A daily summary covers yesterday; a weekly one covers the last full
 * Monday–Sunday week. Each summary goes out once per period.
 */
class NotificationSchedule
{
    public const MODES = ['immediate' => 'Immediately', 'daily' => 'Daily', 'weekly' => 'Weekly'];

    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /** @return array{mode:string,day:int,time:string,timezone:string,skip_empty:bool,business:?int} */
    public static function normalize(?array $saved, array $default): array
    {
        $s = array_merge(['mode' => 'weekly', 'day' => 1, 'time' => '08:00', 'timezone' => BusinessTime::zone(), 'skip_empty' => true, 'business' => null], $default, array_filter((array) $saved, fn ($v) => $v !== null && $v !== ''));
        $time = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $s['time']) ? $s['time'] : '08:00';
        try {
            new \DateTimeZone((string) $s['timezone']);
            $zone = (string) $s['timezone'];
        } catch (\Throwable) {
            $zone = BusinessTime::zone();
        }

        return [
            'mode' => array_key_exists($s['mode'], self::MODES) ? $s['mode'] : 'weekly',
            'day' => min(7, max(1, (int) $s['day'])),
            'time' => $time,
            'timezone' => $zone,
            'skip_empty' => filter_var($s['skip_empty'], FILTER_VALIDATE_BOOLEAN),
            'business' => ($saved['business'] ?? null) ? (int) $saved['business'] : null,
        ];
    }

    /** "Weekly · Friday 5:00 PM" */
    public static function describe(array $s): string
    {
        $at = Carbon::createFromFormat('H:i', $s['time'])->format('g:i A');

        return match ($s['mode']) {
            'immediate' => 'Immediately',
            'daily' => 'Daily · '.$at,
            default => 'Weekly · '.self::DAYS[$s['day']].' '.$at,
        };
    }

    /**
     * The summary period that should be sent at $now, or null if nothing is
     * due. "Due" means the send time for the period has passed and that
     * period has not been sent ($lastKey is the key of the last one sent).
     *
     * @return array{key:string,from:Carbon,to:Carbon,label:string}|null
     */
    public static function due(array $s, Carbon $now, ?string $lastKey): ?array
    {
        if ($s['mode'] === 'immediate') {
            return null;
        }
        $local = $now->copy()->setTimezone($s['timezone']);
        [$h, $m] = array_map('intval', explode(':', $s['time']));
        $sendAt = $local->copy()->setTime($h, $m);
        if ($s['mode'] === 'weekly') {
            $sendAt = $local->copy()->startOfWeek()->addDays($s['day'] - 1)->setTime($h, $m);
        }
        if ($local->lt($sendAt)) {
            return null;
        }
        $period = self::periodFor($s['mode'], $sendAt);

        return $period['key'] === $lastKey ? null : $period;
    }

    /** The period a summary sent at $at covers. */
    public static function periodFor(string $mode, Carbon $at): array
    {
        if ($mode === 'daily') {
            $day = Carbon::parse($at->toDateString())->subDay();

            return ['key' => 'd'.$day->toDateString(), 'from' => $day, 'to' => $day->copy(), 'label' => $day->format('D M j, Y')];
        }
        $from = Carbon::parse($at->toDateString())->startOfWeek()->subWeek();
        $to = $from->copy()->addDays(6);

        return ['key' => 'w'.$from->toDateString(), 'from' => $from, 'to' => $to, 'label' => self::rangeLabel($from, $to)];
    }

    public static function rangeLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('M j, Y');
        }

        return $from->isSameMonth($to)
            ? $from->format('M j').' – '.$to->format('j, Y')
            : $from->format('M j').' – '.$to->format('M j, Y');
    }
}

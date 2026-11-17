<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * One value that changes whenever anything on the fulfillment floor changes
 * (a package packed, a station taken, a flag raised...). Screens ask for it
 * every few seconds — a cache read, no database — and redraw only when it
 * has moved, instead of rebuilding the whole board on a timer.
 */
class FulfillmentPulse
{
    private const KEY = 'fulfillment:pulse';

    public static function version(): string
    {
        try {
            return (string) (Cache::get(self::KEY) ?? self::bump());
        } catch (\Throwable) {
            return '0'; // no cache: screens fall back to their slow timers
        }
    }

    public static function bump(): string
    {
        $v = str_replace('.', '', sprintf('%.6F', microtime(true))).random_int(10, 99);
        try {
            Cache::forever(self::KEY, $v);
        } catch (\Throwable) {
            // A missed bump only delays a screen until its next full refresh.
        }

        return $v;
    }

    /** Models whose saves change what the floor screens show. */
    public static function register(): void
    {
        foreach ([
            \App\Models\FulfillmentEvent::class, \App\Models\FulfillmentRun::class, \App\Models\FulfillmentRunBuyer::class,
            \App\Models\FulfillmentRunPackage::class, \App\Models\FulfillmentFlag::class, \App\Models\FulfillmentStation::class,
            \App\Models\FulfillmentShift::class,
        ] as $model) {
            if (! class_exists($model)) continue;
            // After the commit, so a screen that redraws on the bump sees the change.
            $model::saved(fn () => \Illuminate\Support\Facades\DB::afterCommit(fn () => self::bump()));
            $model::deleted(fn () => \Illuminate\Support\Facades\DB::afterCommit(fn () => self::bump()));
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Values already read during this web request, so the layout, nav and
     * widgets asking for the same keys (brand name, logo, modules…) dozens of
     * times cost one cache read each instead of one per call. The cache store
     * is the database in production, so every read is a query.
     *
     * Web requests only: queue workers and commands live for hours and must
     * see a setting another process changes (a scraper's stop request, a run's
     * state), so they always read through.
     */
    private static array $memo = [];

    /** Tests turn the web-request memo on; null means "web requests only". */
    public static ?bool $memoize = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $memoize = self::$memoize ?? ! app()->runningInConsole();
        if ($memoize) {
            self::preload();
            if (array_key_exists($key, self::$memo)) {
                return self::$memo[$key] ?? $default;
            }
            if (self::$complete && ! isset(self::$large[$key])) {
                return $default; // no row for it
            }
        }

        // Wrapped so a setting that isn't set is cached too: remember() treats a
        // bare null as a miss, which re-queried the table and rewrote the cache
        // row on every read of an unset key. The default stays out of the cache,
        // so two callers with different defaults each get their own.
        $cached = cache()->get("setting:{$key}");
        if (! is_array($cached) || ! array_key_exists('__setting', $cached)) {
            $cached = ['__setting' => true, 'v' => static::where('key', $key)->value('value')];
            cache()->put("setting:{$key}", $cached, 3600);
        }

        if ($memoize) {
            self::$memo[$key] = $cached['v'];
        }

        return $cached['v'] ?? $default;
    }

    private static bool $preloaded = false;

    /**
     * One query for every small setting, the first time a web request asks for
     * any: a page reads ten or so different keys (brand, colours, modules, nav
     * visibility…), each its own cache query otherwise. Keys with no row are
     * remembered as unset. Large values (a scraper run's log) are left to the
     * per-key path so they are only read by the page that wants them.
     */
    private static function preload(): void
    {
        if (self::$preloaded) {
            return;
        }
        self::$preloaded = true;

        try {
            $query = static::query()->toBase();
            $value = $query->getGrammar()->wrap('value');
            $rows = $query->select('key')
                ->selectRaw("CASE WHEN LENGTH({$value}) <= 4000 THEN {$value} END AS small, LENGTH({$value}) AS len")
                ->get();
        } catch (\Throwable) {
            return; // no table yet (installing) — fall back to per-key reads
        }

        $large = [];
        foreach ($rows as $row) {
            if ($row->len !== null && $row->len > 4000) {
                $large[$row->key] = true;
            } else {
                self::$memo[$row->key] = $row->small;
            }
        }
        self::$large = $large;
        self::$complete = true;
    }

    /** Keys too big to preload, and whether the preload saw the whole table. */
    private static array $large = [];
    private static bool $complete = false;

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::get($key);
        if ($val === null) {
            return $default;
        }
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        cache()->forget("setting:{$key}");
        self::$memo[$key] = (string) $value;
        unset(self::$large[$key]);
    }

    /** Forget this request's remembered values (tests, long-lived workers). */
    public static function flushMemo(): void
    {
        self::$memo = [];
        self::$large = [];
        self::$preloaded = false;
        self::$complete = false;
    }
}

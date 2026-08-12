<?php

namespace App\Services;

use App\Models\FeatureFlag;

class FeatureFlagService
{
    /** @var array<string, bool> */
    private static array $memo = [];

    /**
     * Check whether a feature flag is enabled.
     * If the flag is not found in the database, returns true (fail open).
     */
    public static function enabled(string $key): bool
    {
        if (array_key_exists($key, static::$memo)) {
            return static::$memo[$key];
        }

        $flag = FeatureFlag::where('key', $key)->first();

        static::$memo[$key] = $flag !== null ? (bool) $flag->enabled : true;

        return static::$memo[$key];
    }

    /**
     * Clear the per-request memo cache (useful in tests and after flag updates).
     */
    public static function flush(): void
    {
        static::$memo = [];
    }
}

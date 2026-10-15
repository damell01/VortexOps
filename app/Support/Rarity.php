<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Item rarity, like a card's: ★ / ★★ / ★★★ by what one unit costs us.
 *
 * Purely a visual hint. The thresholds are set in Settings → Item rarity;
 * an item under the first one gets no stars at all.
 */
class Rarity
{
    public const SETTING = 'item_rarity_thresholds';

    /** @var array{0: float, 1: float, 2: float} */
    public const DEFAULTS = [50.0, 150.0, 500.0];

    private static ?array $memo = null;

    /** @return array{0: float, 1: float, 2: float} ascending */
    public static function thresholds(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }
        $raw = json_decode((string) Setting::get(self::SETTING, ''), true);
        $values = is_array($raw) && count($raw) === 3 ? array_map('floatval', array_values($raw)) : self::DEFAULTS;
        sort($values);

        return static::$memo = $values;
    }

    public static function flushMemo(): void
    {
        static::$memo = null;
    }

    /** 0 (no stars) to 3. */
    public static function tier(?float $unitCost): int
    {
        if ($unitCost === null || $unitCost <= 0) {
            return 0;
        }
        [$one, $two, $three] = static::thresholds();

        return match (true) {
            $unitCost >= $three => 3,
            $unitCost >= $two => 2,
            $unitCost >= $one => 1,
            default => 0,
        };
    }

    public static function label(int $tier): string
    {
        return [1 => 'Uncommon', 2 => 'Rare', 3 => 'Ultra rare'][$tier] ?? '';
    }
}

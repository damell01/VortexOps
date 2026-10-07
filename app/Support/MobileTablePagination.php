<?php
namespace App\Support;

final class MobileTablePagination
{
    public static function isPhone(): bool
    {
        return preg_match('/Android|iPhone|iPad|Mobile/i', (string) request()->userAgent()) === 1;
    }

    public static function defaultPageSize(int|string $desktop): int|string
    {
        return self::isPhone() ? 5 : $desktop;
    }

    public static function options(array $desktop): array
    {
        if (! self::isPhone()) {
            return $desktop;
        }

        return [5];
    }
}

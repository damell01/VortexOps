<?php

namespace App\Support;

final class ChatQuietHours
{
    public static function isQuiet(bool $enabled, string $start, string $end, string $timezone, ?\DateTimeImmutable $now = null): bool
    {
        if (! $enabled || $start === $end) return false;
        $valid = '/^([01][0-9]|2[0-3]):[0-5][0-9]$/';
        if (! preg_match($valid, $start) || ! preg_match($valid, $end)) return false;

        try {
            $zone = new \DateTimeZone($timezone);
            $clock = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->setTimezone($zone)->format('H:i');
        } catch (\Throwable) {
            return false;
        }

        return $start < $end
            ? ($clock >= $start && $clock < $end)
            : ($clock >= $start || $clock < $end);
    }
}

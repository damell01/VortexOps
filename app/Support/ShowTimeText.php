<?php

namespace App\Support;

/**
 * A show's start time, read from the text of its Seller Hub row ("Sun, Oct 12
 * · 8:00 PM"). The scraper's browser runs in Central time, so the time on the
 * page is already the Central wall-clock time a show's start_time holds.
 *
 * Only 12-hour times with AM/PM count: a bare "2:30:00" in the same row is a
 * duration, not a start. The title is taken out first, so "Friday 8PM Breaks"
 * can't stand in for the real time.
 */
class ShowTimeText
{
    /** @return string|null "H:i:s", or null when the text names no time */
    public static function time(?string $text, ?string $title = null): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if (filled($title)) {
            $text = str_ireplace(trim($title), ' ', $text);
        }

        if (! preg_match('/(?<![\d:])(1[0-2]|0?[1-9])(?::([0-5]\d))?\s*([ap])\.?\s?m\.?(?![a-z])/i', $text, $m)) {
            return null;
        }
        $hour = (int) $m[1] % 12 + (strtolower($m[3]) === 'p' ? 12 : 0);

        return sprintf('%02d:%02d:00', $hour, (int) ($m[2] ?? 0));
    }

    /**
     * The time from what a show was imported with: its Seller Hub row text,
     * or the analytics page's copy of it.
     *
     * @return string|null "Y-m-d H:i:s" on the show's own date
     */
    public static function fromPayload(array $raw, ?string $date, ?string $title = null): ?string
    {
        if (! $date) {
            return null;
        }
        foreach (['text', '_seller_hub_text'] as $key) {
            if ($time = self::time(is_string($raw[$key] ?? null) ? $raw[$key] : null, $title ?? ($raw['title'] ?? null))) {
                return substr($date, 0, 10).' '.$time;
            }
        }

        return null;
    }
}

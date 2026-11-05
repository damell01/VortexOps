<?php

namespace Tests\Unit;

use App\Support\ChatQuietHours;
use PHPUnit\Framework\TestCase;

class ChatQuietHoursTest extends TestCase
{
    public function test_overnight_window_and_boundaries(): void
    {
        $at = fn (string $time) => new \DateTimeImmutable('2026-11-05T'.$time.':00+00:00');
        $this->assertTrue(ChatQuietHours::isQuiet(true, '22:00', '07:00', 'UTC', $at('23:00')));
        $this->assertTrue(ChatQuietHours::isQuiet(true, '22:00', '07:00', 'UTC', $at('06:59')));
        $this->assertFalse(ChatQuietHours::isQuiet(true, '22:00', '07:00', 'UTC', $at('07:00')));
        $this->assertTrue(ChatQuietHours::isQuiet(true, '22:00', '07:00', 'UTC', $at('22:00')));
    }

    public function test_daytime_window_and_opt_out(): void
    {
        $at = new \DateTimeImmutable('2026-11-05T13:00:00+00:00');
        $this->assertTrue(ChatQuietHours::isQuiet(true, '12:00', '14:00', 'UTC', $at));
        $this->assertFalse(ChatQuietHours::isQuiet(false, '12:00', '14:00', 'UTC', $at));
        $this->assertFalse(ChatQuietHours::isQuiet(true, '12:00', '12:00', 'UTC', $at));
        $this->assertFalse(ChatQuietHours::isQuiet(true, 'invalid', '14:00', 'UTC', $at));
        $this->assertFalse(ChatQuietHours::isQuiet(true, '12:00', '14:00', 'Invalid/Zone', $at));
    }

    public function test_user_timezone_is_applied(): void
    {
        $at = new \DateTimeImmutable('2026-11-05T04:30:00+00:00');
        $this->assertTrue(ChatQuietHours::isQuiet(true, '22:00', '07:00', 'America/Chicago', $at));
        $this->assertFalse(ChatQuietHours::isQuiet(true, '22:00', '07:00', 'UTC', $at));
    }
}

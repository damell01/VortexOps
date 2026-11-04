<?php

namespace Tests\Feature;

use App\Models\TimeEntry;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FulfillmentPayableTimeTest extends TestCase
{
    public static function timeCases(): array
    {
        return [
            'clocked time accepted' => ['ok', null, 120],
            'approved adjustment' => ['approved', 90, 90],
            'flagged time excluded' => ['flagged', null, 0],
            'rejected time excluded' => ['rejected', null, 0],
        ];
    }

    #[DataProvider('timeCases')]
    public function test_payable_minutes_follow_approval_status(string $status, ?int $approved, int $expected): void
    {
        $entry = new TimeEntry([
            'status' => $status,
            'clocked_in_at' => Carbon::parse('2026-11-04 09:00:00'),
            'clocked_out_at' => Carbon::parse('2026-11-04 11:00:00'),
            'approved_minutes' => $approved,
        ]);

        $this->assertSame($expected, $entry->payableMinutes());
    }
}

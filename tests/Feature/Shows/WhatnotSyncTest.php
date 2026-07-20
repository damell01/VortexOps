<?php

namespace Tests\Feature\Shows;

use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a production crash: markCompleted()/markFailed() computed
 * duration_seconds via now()->diffInSeconds($this->started_at) — Carbon 3's diffInX
 * methods are not absolute by default, and since $started_at is always in the past
 * relative to now(), the non-absolute result comes out negative. That blew up the
 * unsignedInteger duration_seconds column with a SQLSTATE 22003 out-of-range error,
 * masking whatever the real sync failure was.
 */
class WhatnotSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function makeSync(): WhatnotSync
    {
        $channel = WhatnotChannel::create(['name' => 'Chan', 'status' => 'active']);

        return WhatnotSync::create([
            'whatnot_channel_id' => $channel->id,
            'type'               => 'incremental',
            'status'             => 'running',
            'started_at'         => now()->subSeconds(32),
        ]);
    }

    public function test_mark_completed_stores_a_non_negative_duration(): void
    {
        $sync = $this->makeSync();

        $sync->markCompleted(['shows_created' => 1]);

        $this->assertGreaterThanOrEqual(0, $sync->refresh()->duration_seconds);
    }

    public function test_mark_failed_stores_a_non_negative_duration(): void
    {
        $sync = $this->makeSync();

        $sync->markFailed(new \RuntimeException('boom'));

        $this->assertGreaterThanOrEqual(0, $sync->refresh()->duration_seconds);
        $this->assertEquals('failed', $sync->status);
    }
}

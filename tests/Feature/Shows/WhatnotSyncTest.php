<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotSync;
use App\Services\WhatnotScraper;
use App\Services\WhatnotSyncEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

    /**
     * Regression: syncOrdersForChannel() returned compact('errors', 'errorCount')
     * + ['errors' => $errorCount] — PHP's array union operator keeps the LEFT
     * side's value on a key collision, so 'errors' stayed the (array) error-detail
     * list instead of being overwritten by the int count. syncChannel() then did
     * $counters['error_count'] += $orderResult['errors'], adding an array to an
     * int — a TypeError that crashed every channel sync the moment it reached the
     * order-sync step, even when zero errors actually occurred.
     */
    public function test_sync_channel_completes_without_type_error_during_order_sync(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Chan', 'status' => 'active']);
        $show    = Show::create([
            'title'              => 'Test Show',
            'show_date'          => '2026-07-01',
            'status'             => 'pending_review',
            'created_by'         => 1,
            'whatnot_channel_id' => $channel->id,
            'detail_url'         => 'https://www.whatnot.com/live/x/11111111-1111-1111-1111-111111111111',
        ]);

        $scraper = Mockery::mock(WhatnotScraper::class);
        $scraper->shouldReceive('importShows')->once()->andReturn(['created' => 0, 'updated' => 0]);
        $scraper->shouldReceive('importShowOrders')->once()->with(Mockery::on(fn ($s) => $s->id === $show->id))
            ->andReturn(['created' => 1, 'skipped' => 0, 'updated' => 0]);

        $engine = new WhatnotSyncEngine($scraper);
        $sync   = $engine->syncChannel($channel, 'full');

        $this->assertEquals('completed', $sync->status);
        $this->assertEquals(0, $sync->error_count);
    }
}

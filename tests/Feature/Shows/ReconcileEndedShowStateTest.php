<?php

namespace Tests\Feature\Shows;

use App\Console\Commands\SyncWhatnotReporting;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * reconcileEndedShowState() flags a show as likely-never-happened when it's
 * 12+ hours past air time with zero orders, shipments, units, gross, or net.
 * But every one of those fields reads identically whether a show was truly
 * checked and found empty, or was simply never checked at all — every
 * --analytics-only run skips order/shipment reconciliation entirely.
 * last_synced_at is the one field WhatnotReportingReconciler::reconcileOrders()
 * only stamps once a real order check completes for that show, so requiring
 * it is what separates "confirmed nothing" from "haven't looked yet."
 */
class ReconcileEndedShowStateTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = User::factory()->create()->id;
        $this->channel = WhatnotChannel::create(['name' => 'Test Channel', 'status' => 'active']);
    }

    private function show(array $overrides = []): Show
    {
        return Show::create(array_merge([
            'title' => 'Test show',
            'whatnot_channel_id' => $this->channel->id,
            'show_date' => today()->subDays(2)->toDateString(),
            'created_by' => $this->userId,
        ], $overrides));
    }

    private function reconcile(): void
    {
        $command = new SyncWhatnotReporting();
        $method = new \ReflectionMethod($command, 'reconcileEndedShowState');
        $method->setAccessible(true);

        // The command writes its progress line via $this->line(), which
        // needs an output set up. A NullOutput is enough since we only
        // assert on the show's own state afterward, not the printed line.
        $outputProperty = new \ReflectionProperty($command, 'output');
        $outputProperty->setAccessible(true);
        $outputProperty->setValue($command, new \Symfony\Component\Console\Output\NullOutput());

        $method->invoke($command, [$this->channel->id], Carbon::parse('2026-01-01'));
    }

    public function test_a_checked_show_with_nothing_found_is_flagged(): void
    {
        $show = $this->show(['last_synced_at' => now()->subDay()]);

        $this->reconcile();

        $this->assertStringContainsString(Show::NO_ACTIVITY_FLAG, (string) $show->fresh()->notes);
    }

    public function test_a_show_never_order_checked_is_not_flagged(): void
    {
        $show = $this->show(['last_synced_at' => null]);

        $this->reconcile();

        $this->assertStringNotContainsString(Show::NO_ACTIVITY_FLAG, (string) $show->fresh()->notes);
    }

    public function test_an_upcoming_show_is_not_flagged_even_if_checked_and_empty(): void
    {
        $show = $this->show(['show_date' => today()->addDay()->toDateString(), 'last_synced_at' => now()]);

        $this->reconcile();

        $this->assertStringNotContainsString(Show::NO_ACTIVITY_FLAG, (string) $show->fresh()->notes);
    }
}

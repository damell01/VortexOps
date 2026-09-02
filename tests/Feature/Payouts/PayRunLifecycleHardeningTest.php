<?php

namespace Tests\Feature\Payouts;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\PayRunAutomationService;
use App\Services\PayRunReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayRunLifecycleHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->channel = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);
    }

    public function test_overlapping_pay_runs_are_rejected(): void
    {
        $start = now()->startOfWeek();

        WeeklyPayoutBatch::create([
            'week_start' => $start->toDateString(),
            'week_end' => $start->copy()->endOfWeek()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/overlaps Pay Run/');

        WeeklyPayoutBatch::create([
            'week_start' => $start->copy()->addDays(2)->toDateString(),
            'week_end' => $start->copy()->addWeek()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_automation_only_attaches_payroll_ready_shows(): void
    {
        $streamer = $this->makeStreamer();
        $ready = $this->makeShow('Ready Show');
        $blocked = $this->makeShow('Blocked Show');

        $ready->streamers()->attach($streamer->id, ['is_primary' => true]);
        $blocked->streamers()->attach($streamer->id, ['is_primary' => true]);

        $this->approveReport($ready, $streamer);
        // Blocked Show intentionally has no End of Stream report.

        $result = app(PayRunAutomationService::class)->syncWeek(now());
        $batch = $result['batch'];

        $this->assertTrue($batch->payouts()->where('show_id', $ready->id)->exists());
        $this->assertFalse($batch->payouts()->where('show_id', $blocked->id)->exists());
        $this->assertTrue(collect($result['warnings'])->contains(fn (string $warning) => str_contains($warning, 'Blocked Show')));
    }

    public function test_recalculation_detaches_show_that_became_blocked(): void
    {
        $streamer = $this->makeStreamer();
        $show = $this->makeShow('Changed Show');
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $report = $this->approveReport($show, $streamer);

        $first = app(PayRunAutomationService::class)->syncWeek(now());
        $batch = $first['batch'];
        $payout = $batch->payouts()->where('show_id', $show->id)->firstOrFail();

        $report->update([
            'status' => 'changes_requested',
            'approval_status' => 'rejected',
        ]);

        $second = app(PayRunAutomationService::class)->syncWeek(now());

        $this->assertSame(1, $second['payouts_detached']);
        $this->assertFalse($batch->fresh()->payouts()->where('show_id', $show->id)->exists());
        $this->assertNull($payout->fresh()->weekly_payout_batch_id);
    }

    public function test_source_change_after_calculation_marks_draft_run_stale(): void
    {
        $streamer = $this->makeStreamer();
        $show = $this->makeShow('Freshness Show');
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $report = $this->approveReport($show, $streamer);

        $result = app(PayRunAutomationService::class)->syncWeek(now());
        $batch = $result['batch'];

        $this->assertSame([], app(PayRunReadinessService::class)->problems($batch));

        // Ensure the source timestamp is later than the calculated payout.
        $report->forceFill(['updated_at' => now()->addSecond()])->saveQuietly();

        $problems = app(PayRunReadinessService::class)->problems($batch->fresh());

        $this->assertTrue(collect($problems)->contains(
            fn (string $problem) => str_contains($problem, 'payout is stale')
        ));
    }

    private function makeShow(string $title): Show
    {
        return Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title' => $title,
            'show_date' => now()->toDateString(),
            'gross_revenue' => 500,
            'whatnot_net' => 450,
            'tips' => 0,
            'units_sold' => 10,
            'show_duration' => 60,
            'status' => 'reconciled',
            'created_by' => $this->user->id,
        ]);
    }

    private function makeStreamer(): Streamer
    {
        return Streamer::create([
            'name' => 'Test Streamer',
            'status' => 'active',
            'payout_type' => 'profit_share',
            'payout_cadence' => 'weekly',
            'payout_percentage' => 30,
            'include_tips' => false,
        ]);
    }

    private function approveReport(Show $show, Streamer $streamer): StreamerLogEntry
    {
        return StreamerLogEntry::create([
            'show_id' => $show->id,
            'streamer_id' => $streamer->id,
            'status' => 'admin_approved',
            'approval_status' => 'approved',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'product_cost' => 100,
            'gross_revenue' => 500,
            'hours_streamed' => 1,
        ]);
    }
}

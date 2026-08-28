<?php

namespace Tests\Feature\Payouts;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\PayRunAutomationService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayRunAutomationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private WhatnotChannel $channel;
    private PayRunAutomationService $automation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->channel = WhatnotChannel::create(['name' => 'Automation Channel', 'status' => 'active']);
        $this->automation = app(PayRunAutomationService::class);
    }

    private function makeShow(string $date, ?Streamer $streamer = null): Show
    {
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title' => 'Automation Show',
            'show_date' => $date,
            'gross_revenue' => 1000,
            'whatnot_net' => 900,
            'tips' => 0,
            'units_sold' => 20,
            'show_duration' => 120,
            'status' => 'reconciled',
            'created_by' => $this->user->id,
        ]);

        if ($streamer) {
            $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        }

        return $show;
    }

    private function makeStreamer(float $percentage = 10): Streamer
    {
        return Streamer::create([
            'name' => 'Automation Streamer',
            'member_type' => 'streamer',
            'status' => 'active',
            'payout_type' => 'profit_share',
            'payout_percentage' => $percentage,
            'include_tips' => false,
        ]);
    }

    public function test_sync_week_is_idempotent_for_batch_and_show_payouts(): void
    {
        $streamer = $this->makeStreamer();
        $show = $this->makeShow(now()->toDateString(), $streamer);

        $first = $this->automation->syncWeek(now());
        $second = $this->automation->syncWeek(now());

        $this->assertSame($first['batch']->id, $second['batch']->id);
        $this->assertSame(1, WeeklyPayoutBatch::whereDate('week_start', now()->startOfWeek()->toDateString())->count());
        $this->assertSame(1, Payout::where('show_id', $show->id)->where('streamer_id', $streamer->id)->count());
        $this->assertGreaterThan(0, (float) $second['batch']->fresh()->total_payout);
    }

    public function test_sync_week_never_recalculates_finalized_batch(): void
    {
        $streamer = $this->makeStreamer();
        $this->makeShow(now()->toDateString(), $streamer);
        $result = $this->automation->syncWeek(now());
        $batch = $result['batch'];
        app(PayoutService::class)->finalizeBatch($batch, force: true);
        $before = (float) $batch->fresh()->total_payout;

        $streamer->update(['payout_percentage' => 50]);
        $again = $this->automation->syncWeek(now());

        $this->assertSame('finalized', $again['batch']->status);
        $this->assertSame($before, (float) $again['batch']->fresh()->total_payout);
        $this->assertNotEmpty($again['warnings']);
    }

    public function test_backfill_preview_uses_live_formula_path_but_rolls_back_changes(): void
    {
        $streamer = $this->makeStreamer(10);
        $show = $this->makeShow(now()->subWeek()->toDateString(), $streamer);
        $result = $this->automation->syncWeek($show->show_date);
        $batch = $result['batch'];
        app(PayoutService::class)->finalizeBatch($batch, force: true);

        $historical = $batch->payouts()->first()->calculated_payout;
        $streamer->update(['payout_percentage' => 25]);

        $rows = $this->automation->previewRange($show->show_date, $show->show_date, 'streamer');

        $this->assertCount(1, $rows);
        $this->assertSame('DIFFERENCE', $rows[0]['result']);
        $this->assertNotEquals((float) $historical, (float) $rows[0]['calculated_amount']);

        $batch->refresh();
        $this->assertSame('finalized', $batch->status);
        $this->assertEqualsWithDelta((float) $historical, (float) $batch->payouts()->first()->calculated_payout, 0.01);
    }

    public function test_missing_team_assignment_is_reported_as_warning(): void
    {
        $this->makeShow(now()->toDateString());
        $result = $this->automation->syncWeek(now());

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('no team member assigned', implode(' ', $result['warnings']));
    }
}

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
            \App\Models\StreamerLogEntry::create([
                'show_id' => $show->id, 'streamer_id' => $streamer->id,
                'status' => 'admin_approved', 'approval_status' => 'approved',
                'submitted_at' => now(), 'reviewed_at' => now(), 'product_cost' => 100,
                'hours_streamed' => 1, 'hours_review' => 'report',
            ]);
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

    private function approvedTip(Show $show, Streamer $streamer, float $amount, string $key): void
    {
        $id = \Illuminate\Support\Facades\DB::table('whatnot_ledger_entries')->insertGetId(['whatnot_channel_id' => $this->channel->id,
            'created_date' => now(), 'amount' => $amount, 'transaction_type' => 'Tip', 'status' => 'Completed', 'dedup_key' => $key,
            'created_at' => now(), 'updated_at' => now()]);
        \Illuminate\Support\Facades\DB::table('ledger_attributions')->insert(['whatnot_ledger_entry_id' => $id, 'category' => 'tips', 'target' => 'show',
            'show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'approved', 'reason' => 'Automatic verified source',
            'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_recalculating_a_run_pays_the_tips_approved_in_the_ledger(): void
    {
        $streamer = Streamer::create(['name' => 'Tipped Splitter', 'member_type' => 'streamer', 'status' => 'active',
            'payout_type' => 'sales_split', 'house_cut_percentage' => 3, 'sales_split_basis' => 'net', 'include_tips' => true]);
        $show = $this->makeShow(now()->toDateString(), $streamer);

        $before = $this->automation->syncWeek(now());
        $this->assertEqualsWithDelta(873.0, (float) Payout::where('show_id', $show->id)->sum('calculated_payout'), 0.001);

        // Two tips matched to the show (shows.tips is still $0).
        $this->approvedTip($show, $streamer, 15, 'tip-a');
        $this->approvedTip($show, $streamer, 10, 'tip-b');
        $after = $this->automation->syncWeek(now());

        $line = Payout::where('show_id', $show->id)->where('streamer_id', $streamer->id)->firstOrFail();
        $this->assertEqualsWithDelta(898.0, (float) $line->calculated_payout, 0.001);
        $this->assertEqualsWithDelta(25.0, (float) $line->tips_included, 0.001);
        $this->assertStringContainsString('+ $25 tips', (string) $line->calculation_notes);
        $this->assertEqualsWithDelta(898.0, (float) $after['batch']->fresh()->total_payout, 0.001);
        $this->assertSame($before['batch']->id, $after['batch']->id);
    }
}

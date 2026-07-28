<?php

namespace Tests\Feature\Payouts;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayoutService $service;
    private Show $show;
    private WhatnotChannel $channel;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayoutService::class);
        $this->user    = User::factory()->create();
        $this->actingAs($this->user);

        $this->channel = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);
        $this->show    = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Test Show',
            'show_date'          => now()->toDateString(),
            'gross_revenue'      => 1000,
            'whatnot_net'        => 900,
            'tips'               => 50,
            'units_sold'         => 25,
            'show_duration'      => 120, // 2 hours
            'status'             => 'reconciled',
            'created_by'         => $this->user->id,
        ]);
    }

    private function makeStreamer(array $attrs): Streamer
    {
        return Streamer::create(array_merge([
            'name'         => 'Test Streamer',
            'status'       => 'active',
            'include_tips' => false,
        ], $attrs));
    }

    public function test_profit_share_payout(): void
    {
        $streamer = $this->makeStreamer([
            'payout_type'       => 'profit_share',
            'payout_percentage' => 35,
        ]);
        $this->show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $payouts = $this->service->calculateForShow($this->show);

        $this->assertCount(1, $payouts);
        $this->assertEquals(315.00, (float) $payouts[0]->calculated_payout); // 900 * 0.35
    }

    public function test_profit_share_with_tips(): void
    {
        $streamer = $this->makeStreamer([
            'payout_type'       => 'profit_share',
            'payout_percentage' => 35,
            'include_tips'      => true,
        ]);
        $this->show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $payouts = $this->service->calculateForShow($this->show);

        // 900 * 0.35 + 50 tips = 365
        $this->assertEquals(365.00, (float) $payouts[0]->calculated_payout);
    }

    public function test_pwe_labels_payout(): void
    {
        $streamer = $this->makeStreamer([
            'payout_type'  => 'pwe_labels',
            'pwe_rate'     => 0.75,
            'label_rate'   => 0.25,
            'hourly_rate'  => 15,
            'include_tips' => false,
        ]);
        $this->show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $payouts = $this->service->calculateForShow($this->show);

        // units_sold=25, so pwe_count=25, label_count=25, hours=2
        // (0.75*25) + (0.25*25) + (15*2) = 18.75 + 6.25 + 30 = 55
        $this->assertEquals(55.00, (float) $payouts[0]->calculated_payout);
        $this->assertEquals(25, $payouts[0]->pwe_count);
        $this->assertEquals(25, $payouts[0]->label_count);
    }

    public function test_pwe_labels_payout_prefers_real_counts_from_streamer_log(): void
    {
        $streamer = $this->makeStreamer([
            'payout_type'  => 'pwe_labels',
            'pwe_rate'     => 0.75,
            'label_rate'   => 0.25,
            'hourly_rate'  => 15,
            'include_tips' => false,
        ]);
        $this->show->streamers()->attach($streamer->id, ['is_primary' => true]);

        StreamerLogEntry::create([
            'show_id'     => $this->show->id,
            'streamer_id' => $streamer->id,
            'status'      => 'admin_approved',
            'pwe_count'   => 10,
            'label_count' => 40,
        ]);

        $payouts = $this->service->calculateForShow($this->show->fresh());

        // (0.75*10) + (0.25*40) + (15*2) = 7.5 + 10 + 30 = 47.5 — not the units_sold estimate.
        $this->assertEquals(47.50, (float) $payouts[0]->calculated_payout);
        $this->assertEquals(10, $payouts[0]->pwe_count);
        $this->assertEquals(40, $payouts[0]->label_count);
        $this->assertStringNotContainsString('estimated', $payouts[0]->calculation_notes);
    }

    public function test_hybrid_payout_with_burden_rate(): void
    {
        $streamer = $this->makeStreamer([
            'payout_type'       => 'hybrid',
            'hourly_rate'       => 20,
            'payout_percentage' => 10,
            'burden_rate_type'  => 'percentage',
            'burden_rate_value' => 10, // 10% burden
            'include_tips'      => true,
        ]);
        $this->show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $payouts = $this->service->calculateForShow($this->show);

        // hourly: 20 * 2 = 40
        // profit: 900 * 0.10 = 90
        // tips: 50
        // base: 40 + 90 + 50 = 180
        // burden (10% of 180): 18
        // total: 198
        $this->assertEquals(198.00, (float) $payouts[0]->calculated_payout);
        $this->assertNotNull($payouts[0]->burden_rate_applied);
    }

    public function test_owner_fee_deducted(): void
    {
        $streamer = $this->makeStreamer([
            'payout_type'                 => 'profit_share',
            'payout_percentage'           => 40,
            'owner_fee_type'              => 'percentage',
            'owner_fee_value'             => 10,
            'owner_fee_deduct_from_payout' => true,
        ]);
        $this->show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $payouts = $this->service->calculateForShow($this->show);

        // base: 900 * 0.40 = 360
        // fee: 360 * 0.10 = 36
        // net: 360 - 36 = 324
        $this->assertEquals(324.00, (float) $payouts[0]->calculated_payout);
        $this->assertEquals(36.00, (float) $payouts[0]->owner_fee_deducted);
    }

    public function test_hourly_payout(): void
    {
        $streamer = $this->makeStreamer(['payout_type' => 'hourly', 'hourly_rate' => 25]);
        $this->show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $payouts = $this->service->calculateForShow($this->show);

        $this->assertEquals(50.00, (float) $payouts[0]->calculated_payout); // 25 * 2 hours
    }

    public function test_primary_streamer_keeps_full_share_on_collab_show(): void
    {
        $s1 = $this->makeStreamer(['name' => 'Streamer 1', 'payout_type' => 'profit_share', 'payout_percentage' => 50]);
        $s2 = $this->makeStreamer(['name' => 'Streamer 2', 'payout_type' => 'profit_share', 'payout_percentage' => 50]);

        $this->show->streamers()->attach($s1->id, ['is_primary' => true]);
        $this->show->streamers()->attach($s2->id, ['is_primary' => false]);

        $payouts = collect($this->service->calculateForShow($this->show))->keyBy('streamer_id');

        $this->assertCount(2, $payouts);
        // Collab shows don't auto-split revenue — the primary streamer gets
        // the full share (900 * 0.50 = 450) and splitting with collaborators
        // is handled manually outside VortexOps, so the non-primary streamer
        // gets $0 from this system.
        $this->assertEquals(450.00, (float) $payouts[$s1->id]->calculated_payout);
        $this->assertEquals(0.00, (float) $payouts[$s2->id]->calculated_payout);
    }

    public function test_multiple_streamers_without_a_primary_flag_falls_back_to_first_attached(): void
    {
        // Data that predates the is_primary flag (or detectStreamers() never
        // set one) shouldn't suddenly zero out every non-first streamer's
        // payout — fall back to treating the first attached streamer as
        // primary so old shows keep computing the way they always did.
        $s1 = $this->makeStreamer(['name' => 'Streamer 1', 'payout_type' => 'profit_share', 'payout_percentage' => 50]);
        $s2 = $this->makeStreamer(['name' => 'Streamer 2', 'payout_type' => 'profit_share', 'payout_percentage' => 50]);

        $this->show->streamers()->attach($s1->id);
        $this->show->streamers()->attach($s2->id);

        $payouts = collect($this->service->calculateForShow($this->show))->keyBy('streamer_id');

        $this->assertEquals(450.00, (float) $payouts[$s1->id]->calculated_payout);
        $this->assertEquals(0.00, (float) $payouts[$s2->id]->calculated_payout);
    }

    public function test_returns_empty_when_no_streamers(): void
    {
        $payouts = $this->service->calculateForShow($this->show);
        $this->assertEmpty($payouts);
    }
}

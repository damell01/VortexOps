<?php

namespace Tests\Feature\Businesses;

use App\Models\Business;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use App\Support\BusinessTransfer;
use App\Support\PaymentStructure;
use App\Support\PayRunSchedules;
use App\Support\UserPayFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estill Distribution's two kinds of streamer, its monthly tips and the
 * transfer to its separate account.
 */
class EstillPayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private WhatnotChannel $channel;
    private Business $estill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->owner);
        $this->channel = WhatnotChannel::create(['name' => 'Estill Cards', 'status' => 'active']);
        $this->estill = Business::where('short_name', 'Estill')->firstOrFail();

        PaymentStructure::saveDefaults('streamer', [
            'payout_type' => 'sales_split', 'payout_cadence' => 'weekly', 'house_cut_percentage' => 20,
            'sales_split_basis' => 'net', 'include_tips' => true,
        ], $this->estill->id);
    }

    private function member(string $name, array $overrides = []): Streamer
    {
        $member = Streamer::create([
            'name' => $name, 'member_type' => 'streamer', 'streamer_type' => 'in_house', 'status' => 'active',
            'business_id' => $this->estill->id, 'compensation_override_fields' => [],
        ]);
        if ($overrides) {
            PaymentStructure::saveOverrides($member, $overrides);
        }

        return $member->fresh();
    }

    private function show(Streamer $streamer, array $extra = []): Show
    {
        $show = Show::create($extra + [
            'whatnot_channel_id' => $this->channel->id, 'title' => "Show with {$streamer->name}",
            'show_date' => now()->toDateString(), 'gross_revenue' => 1200, 'whatnot_net' => 1000, 'tips' => 0,
            'status' => 'reconciled', 'created_by' => $this->owner->id,
        ]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        return $show->load('streamers');
    }

    private function hourlyStreamer(array $extra = []): Streamer
    {
        return $this->member('Estill Hourly', $extra + [
            'payout_type' => 'shift_share', 'hourly_rate' => 20, 'pwe_rate' => 1, 'label_rate' => 2,
            'payout_percentage' => 10, 'burden_rate_type' => 'flat', 'burden_rate_value' => 50, 'include_tips' => false,
        ]);
    }

    public function test_hourly_streamer_is_paid_hours_shipments_and_a_share_of_what_is_left(): void
    {
        $streamer = $this->hourlyStreamer();
        $show = $this->show($streamer);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved',
            'hours_streamed' => 4, 'product_cost' => 300, 'pwe_count' => 10, 'label_count' => 5, 'number_of_shipments' => 15]);

        $lines = collect(app(PayoutService::class)->calculateForShow($show->fresh('streamers')))->keyBy('payout_type');

        // Hourly 4 × $20 = $80. Shipments 10 × $1 + 5 × $2 = $20 — wages, paid weekly.
        // Share: ($1,000 − $300 − $50 − $80 − $20) = $550 × 10% = $55 — paid on the profit-share run, like VCX.
        $share = $lines['shift_share'];
        $wages = $lines['show_hours'];
        $this->assertEqualsWithDelta(55.00, (float) $share->calculated_payout, 0.001);
        $this->assertEqualsWithDelta(550.00, (float) $share->net_revenue_basis, 0.001);
        $this->assertStringContainsString('$550.00 × 10% = $55.00', $share->calculation_notes);
        $this->assertEqualsWithDelta(100.00, (float) $wages->calculated_payout, 0.001);
        $this->assertSame(10, $wages->pwe_count);
        $this->assertSame(5, $wages->label_count);
        $this->assertSame('share', PayRunSchedules::kindOf($share));
        $this->assertSame('wages', PayRunSchedules::kindOf($wages));
    }

    public function test_a_burden_can_be_a_percentage_of_net_sales(): void
    {
        $streamer = $this->hourlyStreamer(['burden_rate_type' => 'percentage', 'burden_rate_value' => 5]);
        $show = $this->show($streamer);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved',
            'hours_streamed' => 4, 'product_cost' => 300, 'pwe_count' => 10, 'label_count' => 5]);

        $lines = collect(app(PayoutService::class)->calculateForShow($show->fresh('streamers')))->keyBy('payout_type');

        // Burden 5% of $1,000 = $50, so the same $55 share.
        $this->assertEqualsWithDelta(55.00, (float) $lines['shift_share']->calculated_payout, 0.001);
    }

    public function test_a_show_that_does_not_cover_its_costs_pays_no_share(): void
    {
        $streamer = $this->hourlyStreamer();
        $show = $this->show($streamer, ['whatnot_net' => 200]);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved',
            'hours_streamed' => 4, 'product_cost' => 300, 'pwe_count' => 10, 'label_count' => 5]);

        $lines = collect(app(PayoutService::class)->calculateForShow($show->fresh('streamers')))->keyBy('payout_type');

        $this->assertEqualsWithDelta(0.00, (float) $lines['shift_share']->calculated_payout, 0.001);
        $this->assertStringContainsString('no share', $lines['shift_share']->calculation_notes);
        $this->assertEqualsWithDelta(100.00, (float) $lines['show_hours']->calculated_payout, 0.001);
    }

    public function test_a_special_show_can_keep_a_different_percent_from_an_independent_streamer(): void
    {
        $streamer = $this->member('Independent', ['include_tips' => false]);
        $usual = $this->show($streamer);
        $special = $this->show($streamer, ['title' => 'Special', 'house_cut_override' => 35]);

        [$normal] = app(PayoutService::class)->calculateForShow($usual);
        [$override] = app(PayoutService::class)->calculateForShow($special);

        $this->assertEqualsWithDelta(800.00, (float) $normal->calculated_payout, 0.001);
        $this->assertEqualsWithDelta(650.00, (float) $override->calculated_payout, 0.001);
        $this->assertStringContainsString("this show's %", $override->calculation_notes);
    }

    public function test_tips_go_on_their_own_line_when_the_business_pays_them_monthly(): void
    {
        $this->estill->update(['tips_monthly' => true]);
        $streamer = $this->member('Tipped');
        $show = $this->show($streamer, ['tips' => 40]);

        $lines = collect(app(PayoutService::class)->calculateForShow($show))->keyBy('payout_type');

        $this->assertEqualsWithDelta(800.00, (float) $lines['sales_split']->calculated_payout, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $lines['sales_split']->tips_included, 0.001);
        $this->assertEqualsWithDelta(40.00, (float) $lines['show_tips']->calculated_payout, 0.001);
        $this->assertSame('tips', PayRunSchedules::kindOf($lines['show_tips']));

        // Weekly runs leave the tips for the Tips run, which pays nothing else.
        $weekly = PayRunSchedules::scopeToKind(Payout::query(), null)->pluck('payout_type')->all();
        $tips = PayRunSchedules::scopeToKind(Payout::query(), 'tips')->pluck('payout_type')->all();
        $this->assertSame(['sales_split'], $weekly);
        $this->assertSame(['show_tips'], $tips);
    }

    public function test_tips_stay_on_the_weekly_line_otherwise(): void
    {
        $streamer = $this->member('Tipped Weekly');
        $show = $this->show($streamer, ['tips' => 40]);

        $lines = app(PayoutService::class)->calculateForShow($show);

        $this->assertCount(1, $lines);
        $this->assertEqualsWithDelta(840.00, (float) $lines[0]->calculated_payout, 0.001);
    }

    public function test_a_monthly_tips_run_does_not_clash_with_weekly_runs(): void
    {
        WeeklyPayoutBatch::create(['week_start' => now()->startOfWeek()->toDateString(), 'week_end' => now()->endOfWeek()->toDateString(),
            'status' => 'draft', 'business_id' => $this->estill->id]);

        $tips = WeeklyPayoutBatch::create(['week_start' => now()->startOfMonth()->toDateString(), 'week_end' => now()->endOfMonth()->toDateString(),
            'status' => 'draft', 'business_id' => $this->estill->id, 'kind' => 'tips']);

        $this->assertTrue($tips->exists);
    }

    public function test_the_transfer_is_product_cost_plus_what_the_business_kept(): void
    {
        $streamer = $this->member('Independent', ['include_tips' => false]);
        $show = $this->show($streamer);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved', 'product_cost' => 300]);
        $run = WeeklyPayoutBatch::create(['week_start' => now()->startOfWeek()->toDateString(), 'week_end' => now()->endOfWeek()->toDateString(),
            'status' => 'draft', 'business_id' => $this->estill->id]);
        foreach (app(PayoutService::class)->calculateForShow($show) as $line) {
            $line->update(['weekly_payout_batch_id' => $run->id]);
        }

        $transfer = BusinessTransfer::forRun($run->fresh());

        // $300 product + ($1,000 net − $800 paid) = $500.
        $this->assertEqualsWithDelta(300.00, $transfer['product_cost'], 0.001);
        $this->assertEqualsWithDelta(200.00, $transfer['business_revenue'], 0.001);
        $this->assertEqualsWithDelta(500.00, $transfer['transfer'], 0.001);
    }

    public function test_the_profile_pay_section_sets_up_an_hourly_streamer(): void
    {
        $streamer = $this->member('From Profile');

        UserPayFields::apply($streamer, [
            UserPayFields::MODEL => 'shift_share',
            UserPayFields::key('payout_percentage') => 12,
            UserPayFields::key('hourly_rate') => 18,
            UserPayFields::key('pwe_rate') => 0.75,
            UserPayFields::key('label_rate') => 1.5,
            UserPayFields::key('burden_rate_value') => 25,
        ]);

        $effective = PaymentStructure::resolve($streamer->fresh())['effective'];
        $this->assertSame('shift_share', $effective['payout_type']);
        $this->assertEqualsWithDelta(18.0, $effective['hourly_rate'], 0.001);
        $this->assertEqualsWithDelta(0.75, $effective['pwe_rate'], 0.001);
        $this->assertEqualsWithDelta(1.5, $effective['label_rate'], 0.001);
        $this->assertEqualsWithDelta(25.0, $effective['burden_rate_value'], 0.001);
        $this->assertSame('flat', $effective['burden_rate_type']);
    }
}

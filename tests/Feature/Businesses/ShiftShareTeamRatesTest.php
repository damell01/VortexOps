<?php

namespace Tests\Feature\Businesses;

use App\Filament\Pages\PaymentStructures;
use App\Models\Business;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use App\Support\BusinessContext;
use App\Support\PaymentStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** A business's hourly + shipments + profit share rates, set once for its team. */
class ShiftShareTeamRatesTest extends TestCase
{
    use RefreshDatabase;

    private Business $estill;
    private Business $vcx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
        $this->estill = Business::where('short_name', 'Estill')->firstOrFail();
        $this->vcx = Business::where('short_name', 'VCX')->firstOrFail();
        PaymentStructure::saveDefaults('streamer', ['payout_type' => 'sales_split', 'house_cut_percentage' => 20, 'sales_split_basis' => 'net', 'include_tips' => true], $this->estill->id);
        PaymentStructure::saveShiftDefaults(['hourly_rate' => 20, 'pwe_rate' => 1, 'label_rate' => 2, 'payout_percentage' => 10, 'burden_rate_type' => 'flat', 'burden_rate_value' => 50], $this->estill->id);
    }

    private function person(string $name, array $overrides = [], ?Business $business = null): Streamer
    {
        $member = Streamer::create(['name' => $name, 'member_type' => 'streamer', 'streamer_type' => 'in_house', 'status' => 'active',
            'business_id' => ($business ?? $this->estill)->id, 'compensation_override_fields' => []]);
        if ($overrides) {
            PaymentStructure::saveOverrides($member, $overrides);
        }

        return $member->fresh();
    }

    public function test_choosing_the_pay_type_is_enough_the_team_rates_fill_the_rest(): void
    {
        $e = $this->person('Jay', ['payout_type' => 'shift_share'])->effectiveCompensation()['effective'];

        $this->assertSame('shift_share', $e['payout_type']);
        $this->assertEquals([20.0, 1.0, 2.0, 10.0, 50.0], [$e['hourly_rate'], $e['pwe_rate'], $e['label_rate'], $e['payout_percentage'], $e['burden_rate_value']]);
    }

    public function test_someone_paid_differently_keeps_their_own_rate(): void
    {
        $e = $this->person('Kim', ['payout_type' => 'shift_share', 'pwe_rate' => 1.5])->effectiveCompensation()['effective'];

        $this->assertEquals(1.5, $e['pwe_rate']);
        $this->assertEquals(2.0, $e['label_rate']);
    }

    public function test_independents_stay_on_the_sales_split(): void
    {
        $e = $this->person('Sam')->effectiveCompensation()['effective'];

        $this->assertSame('sales_split', $e['payout_type']);
        $this->assertEquals(20.0, $e['house_cut_percentage']);
    }

    public function test_another_business_is_not_affected(): void
    {
        $e = $this->person('Casey', ['payout_type' => 'shift_share'], $this->vcx)->effectiveCompensation()['effective'];

        $this->assertNull($e['pwe_rate']);
    }

    public function test_it_pays_a_show_on_the_team_rates(): void
    {
        $jay = $this->person('Jay', ['payout_type' => 'shift_share']);
        $channel = WhatnotChannel::create(['name' => 'Estill Cards', 'status' => 'active']);
        $show = Show::create(['whatnot_channel_id' => $channel->id, 'title' => 'Show', 'show_date' => now()->toDateString(),
            'gross_revenue' => 1200, 'whatnot_net' => 1000, 'tips' => 0, 'status' => 'reconciled']);
        $show->streamers()->attach($jay->id, ['is_primary' => true]);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $jay->id, 'status' => 'admin_approved',
            'hours_streamed' => 4, 'product_cost' => 300, 'pwe_count' => 10, 'label_count' => 5]);

        $total = collect(app(PayoutService::class)->calculateForShow($show->fresh('streamers')))->sum(fn ($p) => (float) $p->calculated_payout);

        $this->assertEqualsWithDelta(155.00, $total, 0.001); // $80 hourly + $20 shipments + $55 share
    }

    public function test_the_team_rates_page_saves_them_for_the_chosen_business_and_can_make_it_the_team_default(): void
    {
        BusinessContext::setActive($this->vcx->id);

        Livewire::test(PaymentStructures::class)
            ->set('streamer.payout_type', 'shift_share')
            ->set('shift.hourly_rate', 18)->set('shift.pwe_rate', .75)->set('shift.label_rate', 1.5)
            ->set('shift.payout_percentage', 12)->set('shift.burden_rate_value', '')
            ->call('saveRates')->assertHasNoErrors()
            ->assertSee('Hourly + shipments + profit share');

        $this->assertSame('shift_share', PaymentStructure::defaults('streamer', $this->vcx->id)['payout_type']);
        $this->assertEquals(.75, PaymentStructure::shiftDefaults($this->vcx->id)['pwe_rate']);
        $this->assertEquals(1, PaymentStructure::shiftDefaults($this->estill->id)['pwe_rate'], 'Estill keeps its own');

        $e = $this->person('Riley', [], $this->vcx)->effectiveCompensation()['effective'];
        $this->assertSame('shift_share', $e['payout_type']);
        $this->assertEquals([18.0, 12.0], [$e['hourly_rate'], $e['payout_percentage']]);
    }

    public function test_the_hourly_type_needs_an_hourly_rate_and_a_share_when_it_is_the_team_default(): void
    {
        BusinessContext::setActive($this->vcx->id);

        Livewire::test(PaymentStructures::class)
            ->set('streamer.payout_type', 'shift_share')->set('shift.hourly_rate', '')->set('shift.payout_percentage', '')
            ->call('saveRates')->assertHasErrors(['shift.hourly_rate', 'shift.payout_percentage']);
    }
}

<?php

namespace Tests\Feature\Payouts;

use App\Filament\Pages\PaymentStructures;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use App\Support\PaymentStructure;
use App\Support\PayoutFormula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PayoutFormulaBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_arithmetic_percentages_and_existing_variable_names(): void
    {
        $values = PayoutFormula::samples();
        $this->assertEquals(44, PayoutFormula::evaluate('(whatnot_net - product_cost) * (8 / 100)', $values));
        $this->assertEquals(68.5, PayoutFormula::evaluate('hours_worked * hourly_rate + pwe_count * pwe_rate + label_count * label_rate', $values));
        $this->assertEquals(88, PayoutFormula::evaluate('streamer_share_net * payout_percentage / 100 + tip_share', $values));
        $this->assertEquals(-5.5, PayoutFormula::evaluate('-(2 + 3.5)', $values));
        $this->assertEquals(2, PayoutFormula::evaluate('8 / 2 / 2', $values));
        $this->assertEquals(.5, PayoutFormula::evaluate('.25 + 0.25', $values));
    }

    public function test_parser_rejects_malformed_or_executable_input_without_skipping_text(): void
    {
        foreach (['hours_worked hourly_rate', '1.2.3', '1 +', '(1 + 2', '2 (3)', '1 + 2)', 'unknown * 2', 'phpinfo()', '1; 2', '8%', '1e3', '1 ** 2', '1,000', ''] as $formula) {
            try {
                PayoutFormula::validate($formula);
                $this->fail('Accepted invalid formula: ' . $formula);
            } catch (\RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->expectException(\RuntimeException::class);
        PayoutFormula::evaluate('whatnot_net / (hours_worked - 4)', PayoutFormula::samples());
    }

    public function test_preview_uses_samples_and_never_writes_payroll(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $page = new PaymentStructures;
        $before = PaymentStructure::defaults('streamer');
        $this->assertSame(['amount' => 44.0], $page->previewPayoutFormula('(whatnot_net - product_cost) * (8 / 100)', PayoutFormula::samples()));
        $this->assertArrayHasKey('error', $page->previewPayoutFormula('hours_worked +', PayoutFormula::samples()));
        $this->assertArrayHasKey('error', $page->previewPayoutFormula('1 / 0', PayoutFormula::samples()));
        $this->assertArrayHasKey('error', $page->previewPayoutFormula('1', ['unavailable' => 2]));
        $this->assertSame($before, PaymentStructure::defaults('streamer'));
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_invalid_team_and_individual_formulas_cannot_be_saved(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        Livewire::test(PaymentStructures::class)
            ->set('streamer.custom_payout_formula', 'hours_worked hourly_rate')
            ->call('save')->assertHasErrors(['streamer.custom_payout_formula']);
        $member = Streamer::create(['name' => 'Formula member', 'payout_type' => 'profit_share', 'member_type' => 'streamer', 'status' => 'active']);
        Livewire::test(PaymentStructures::class)->call('editMember', $member->id)
            ->set('member_override_enabled.custom_payout_formula', true)
            ->set('member_override_values.custom_payout_formula', 'unknown + 1')
            ->call('saveMemberOverrides')->assertHasErrors(['member_override_values.custom_payout_formula']);
        $this->assertNull($member->fresh()->custom_payout_formula);
    }

    public function test_saved_individual_formula_drives_real_show_payout_and_share_routing(): void
    {
        $user = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($user);
        $member = Streamer::create(['name' => 'Formula member', 'payout_type' => 'profit_share', 'member_type' => 'streamer', 'status' => 'active', 'include_tips' => false]);
        $formula = '(whatnot_net - product_cost) * (8 / 100) + pwe_count * pwe_rate';
        Livewire::test(PaymentStructures::class)->call('editMember', $member->id)
            ->set('member_override_enabled.custom_payout_formula', true)
            ->set('member_override_values.custom_payout_formula', $formula)
            ->set('member_override_enabled.pwe_rate', true)->set('member_override_values.pwe_rate', .10)
            ->call('saveMemberOverrides')->assertHasNoErrors();
        $channel = WhatnotChannel::create(['name' => 'Formula channel', 'status' => 'active']);
        $show = Show::create(['title' => 'Formula show', 'whatnot_channel_id' => $channel->id, 'show_date' => now()->toDateString(), 'gross_revenue' => 1000, 'whatnot_net' => 850, 'status' => 'reconciled', 'created_by' => $user->id]);
        $show->streamers()->attach($member->id, ['is_primary' => true]);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $member->id, 'product_cost' => 300, 'pwe_count' => 10, 'hours_streamed' => 4]);
        $payout = app(PayoutService::class)->calculateForShow($show->fresh())[0];
        $this->assertSame('custom_formula', $payout->payout_type);
        $this->assertEquals(45, (float) $payout->calculated_payout);
        $this->assertSame(1, Payout::query()->tap(fn ($q) => \App\Support\PayRunSchedules::scopeToKind($q, 'share'))->count());
        $this->assertSame(0, Payout::query()->tap(fn ($q) => \App\Support\PayRunSchedules::scopeToKind($q, 'wages'))->count());
        // Finalized lines are never recalculated after an administrator edits the formula.
        $payout->update(['status' => 'paid']);
        PaymentStructure::saveOverrides($member->fresh(), ['payout_type' => 'custom_formula', 'custom_payout_formula' => '999']);
        app(PayoutService::class)->calculateForShow($show->fresh());
        $this->assertEquals(45, (float) $payout->fresh()->calculated_payout);
    }
}

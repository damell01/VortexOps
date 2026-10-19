<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\PaymentStructures;
use App\Models\Streamer;
use App\Models\User;
use App\Support\PaymentStructure;
use App\Support\UserPayFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Individual formulas now live in Rates & Adjustments, beside team defaults. */
class StreamerFormulaBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
        PaymentStructure::saveDefaults('streamer', ['payout_type' => 'profit_share', 'payout_percentage' => 8, 'custom_payout_formula' => null]);
    }

    private function member(): Streamer
    {
        return Streamer::create(['name' => 'Combo Streamer', 'status' => 'active', 'payout_type' => 'profit_share']);
    }

    private function editor(Streamer $member)
    {
        return Livewire::test(PaymentStructures::class)->call('editMember', $member->id);
    }

    public function test_an_individual_formula_is_saved_without_changing_another_member(): void
    {
        $member = $this->member();
        $other = $this->member();
        $formula = 'package_rate + (payout_percentage / 100) * streamer_share_net';
        $this->editor($member)
            ->set('member_override_enabled.custom_payout_formula', true)
            ->set('member_override_values.custom_payout_formula', $formula)
            ->call('saveMemberOverrides')->assertHasNoErrors();
        $this->assertSame($formula, $member->fresh()->custom_payout_formula);
        $this->assertSame('custom_formula', $member->fresh()->payout_type);
        $this->assertNull($other->fresh()->custom_payout_formula);
    }

    public function test_an_invalid_formula_is_rejected_and_existing_pay_is_preserved(): void
    {
        $member = $this->member();
        $this->editor($member)
            ->set('member_override_enabled.custom_payout_formula', true)
            ->set('member_override_values.custom_payout_formula', 'unknown_pay_variable + 1')
            ->call('saveMemberOverrides')->assertHasErrors(['member_override_values.custom_payout_formula']);
        $this->assertNull($member->fresh()->custom_payout_formula);
    }

    public function test_reopening_the_editor_prefills_the_saved_formula(): void
    {
        $member = $this->member();
        PaymentStructure::saveOverrides($member, ['custom_payout_formula' => 'package_rate + tip_share', 'payout_type' => 'custom_formula']);
        $this->editor($member->fresh())
            ->assertSet('member_override_enabled.custom_payout_formula', true)
            ->assertSet('member_override_values.custom_payout_formula', 'package_rate + tip_share');
    }

    public function test_profile_pay_edits_preserve_a_formula_that_the_profile_does_not_display(): void
    {
        $member = $this->member();
        PaymentStructure::saveOverrides($member, ['custom_payout_formula' => 'package_rate + tip_share', 'payout_type' => 'custom_formula']);
        $data = UserPayFields::prefill($member->fresh());
        $data['pay_payout_percentage'] = 9;
        UserPayFields::apply($member->fresh(), $data);
        $this->assertSame('package_rate + tip_share', $member->fresh()->custom_payout_formula);
        $this->assertSame('custom_formula', $member->fresh()->payout_type);
    }

    public function test_disabling_the_formula_override_restores_the_team_formula(): void
    {
        $member = $this->member();
        PaymentStructure::saveOverrides($member, ['custom_payout_formula' => 'package_rate', 'payout_type' => 'custom_formula']);
        $this->editor($member->fresh())->set('member_override_enabled.custom_payout_formula', false)
            ->call('saveMemberOverrides')->assertHasNoErrors();
        $this->assertNull($member->fresh()->compensationValue('custom_payout_formula'));
        $this->assertSame('profit_share', $member->fresh()->compensationValue('payout_type'));
    }

    public function test_streamers_cannot_open_the_compensation_editor(): void
    {
        Role::findOrCreate('streamer', 'web');
        $user = User::factory()->create();
        $user->assignRole('streamer');
        $this->actingAs($user);
        Livewire::test(PaymentStructures::class)->assertForbidden();
    }
}

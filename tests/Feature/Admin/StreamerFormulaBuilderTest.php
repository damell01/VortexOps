<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\StreamerResource\Pages\CreateStreamer;
use App\Filament\Resources\StreamerResource\Pages\EditStreamer;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Custom Formula payout type gets a checkbox "formula builder" so admins
 * don't have to hand-write formulas like `package_rate + (payout_percentage
 * / 100) * streamer_share_net` for combos (Package + Profit Share, PWE +
 * Labels + Profit Share, etc).
 */
class StreamerFormulaBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_checking_package_and_profit_share_assembles_the_formula(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(CreateStreamer::class)
            ->fillForm([
                'name' => 'Combo Streamer',
                'payout_type' => 'custom_formula',
            ])
            ->set('data.component_package', true)
            ->set('data.component_profit_share', true)
            ->assertSet('data.custom_payout_formula', 'package_rate + (payout_percentage / 100) * streamer_share_net');
    }

    public function test_unchecking_a_component_removes_it_from_the_formula(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(CreateStreamer::class)
            ->fillForm(['name' => 'Combo Streamer', 'payout_type' => 'custom_formula'])
            ->set('data.component_package', true)
            ->set('data.component_tips', true)
            ->assertSet('data.custom_payout_formula', 'package_rate + tip_share')
            ->set('data.component_package', false)
            ->assertSet('data.custom_payout_formula', 'tip_share');
    }

    public function test_pwe_and_labels_and_profit_share_combo(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(CreateStreamer::class)
            ->fillForm(['name' => 'Fulfillment Combo', 'payout_type' => 'custom_formula'])
            ->set('data.component_pwe', true)
            ->set('data.component_labels', true)
            ->set('data.component_profit_share', true)
            ->assertSet(
                'data.custom_payout_formula',
                '(payout_percentage / 100) * streamer_share_net + pwe_rate + label_rate'
            );
    }

    public function test_rate_fields_become_visible_only_for_checked_components(): void
    {
        Livewire::actingAs($this->admin());

        $component = Livewire::test(CreateStreamer::class)
            ->fillForm(['name' => 'X', 'payout_type' => 'custom_formula']);

        $component->assertFormFieldIsHidden('package_rate');

        $component->set('data.component_package', true)
            ->assertFormFieldIsVisible('package_rate');
    }

    public function test_checkboxes_are_not_persisted_as_streamer_attributes(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(CreateStreamer::class)
            ->fillForm([
                'name' => 'Persist Check',
                'payout_type' => 'custom_formula',
                'payout_cadence' => 'weekly',
            ])
            ->set('data.component_package', true)
            ->set('data.package_rate', 50)
            ->call('create')
            ->assertHasNoFormErrors();

        $streamer = Streamer::where('name', 'Persist Check')->firstOrFail();
        $this->assertSame('package_rate', $streamer->custom_payout_formula);
        $this->assertEqualsWithDelta(50.0, (float) $streamer->package_rate, 0.01);
        $this->assertArrayNotHasKey('component_package', $streamer->getAttributes());
    }

    public function test_edit_page_precheck_reflects_saved_formula(): void
    {
        $streamer = Streamer::create([
            'name' => 'Existing', 'status' => 'active',
            'payout_type' => 'custom_formula',
            'custom_payout_formula' => 'package_rate + tip_share',
        ]);

        Livewire::actingAs($this->admin());

        Livewire::test(EditStreamer::class, ['record' => $streamer->getRouteKey()])
            ->assertFormSet(['component_package' => true, 'component_tips' => true, 'component_profit_share' => false]);
    }
}

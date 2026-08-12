<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\AppSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DefaultOwnerFeeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@vortex.com']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_saving_a_default_fee_persists_and_reloads(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(AppSettings::class)
            ->set('default_owner_fee_type', 'percentage')
            ->set('default_owner_fee_value', '7.5')
            ->set('default_owner_fee_deduct_from_payout', false)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertEquals('percentage', Setting::get('default_owner_fee_type'));
        $this->assertEquals('7.5', Setting::get('default_owner_fee_value'));
        $this->assertEquals('0', Setting::get('default_owner_fee_deduct_from_payout'));

        // Reloading the page picks the saved values back up.
        Livewire::test(AppSettings::class)
            ->assertSet('default_owner_fee_type', 'percentage')
            ->assertSet('default_owner_fee_value', '7.5')
            ->assertSet('default_owner_fee_deduct_from_payout', false);
    }

    public function test_clearing_the_fee_type_clears_the_stored_value_too(): void
    {
        Setting::set('default_owner_fee_type', 'flat');
        Setting::set('default_owner_fee_value', '25');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(AppSettings::class)
            ->set('default_owner_fee_type', null)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertEquals('', Setting::get('default_owner_fee_type'));
        $this->assertEquals('', Setting::get('default_owner_fee_value'));
    }
}

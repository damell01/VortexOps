<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\StreamerResource\Pages\CreateStreamer;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Long forms are short screens: adding someone walks through steps, and
 * editing them opens on tabs (Profile, Pay, ...) instead of one long page.
 */
class ProfileTabsAndWizardsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_adding_a_team_member_is_a_wizard(): void
    {
        $this->actingAs($this->admin());

        $html = $this->get('/admin/streamers/create')->assertOk()->getContent();
        $this->assertStringContainsString('fi-sc-wizard', $html);
        foreach (['Profile', 'Pay', 'Bank routing', 'Login'] as $step) {
            $this->assertStringContainsString($step, $html);
        }
    }

    public function test_the_first_step_must_be_filled_before_moving_on(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(CreateStreamer::class)
            ->goToNextWizardStep()
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_editing_a_team_member_opens_on_tabs_with_pay_its_own_tab(): void
    {
        $this->actingAs($this->admin());
        $member = Streamer::create(['name' => 'Ana', 'member_type' => 'streamer', 'streamer_type' => 'in_house', 'status' => 'active',
            'compensation_override_fields' => [], 'payout_type' => 'profit_share', 'payout_percentage' => 10]);

        $html = $this->get('/admin/streamers/'.$member->id.'/edit')->assertOk()->getContent();
        $this->assertStringContainsString('fi-sc-tabs', $html);
        foreach (['Profile', 'Pay', 'Bank routing', 'Inventory'] as $tab) {
            $this->assertStringContainsString('>'.$tab, preg_replace('/>\s+/', '>', $html));
        }
        $this->assertStringNotContainsString('Create a login for this team member', $html);
    }

    public function test_editing_a_user_opens_on_tabs(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $html = $this->get('/admin/users/'.$admin->id.'/edit')->assertOk()->getContent();
        $this->assertStringContainsString('fi-sc-tabs', $html);
        $this->assertStringContainsString('Role &amp; access', $html);
    }
}

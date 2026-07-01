<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
    }

    public function test_owner_check_returns_true_for_owner_email(): void
    {
        config(['app.owner_email' => 'owner@example.com']);
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $this->assertTrue($owner->isOwner());
    }

    public function test_owner_check_returns_false_for_other_email(): void
    {
        config(['app.owner_email' => 'owner@example.com']);
        $user = User::factory()->create(['email' => 'someone@else.com']);
        $this->assertFalse($user->isOwner());
    }

    public function test_owner_check_returns_false_when_owner_email_not_configured(): void
    {
        config(['app.owner_email' => null]);
        $user = User::factory()->create(['email' => 'anyone@example.com']);
        $this->assertFalse($user->isOwner());
    }

    public function test_module_is_enabled_when_in_settings(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams', 'inventory']));
        AdminModules::flushMemo();

        $this->assertTrue(AdminModules::isEnabled('streams'));
        $this->assertTrue(AdminModules::isEnabled('inventory'));
        $this->assertFalse(AdminModules::isEnabled('payouts'));
    }

    public function test_module_defaults_used_when_no_setting(): void
    {
        Setting::where('key', 'enabled_admin_modules')->delete();
        AdminModules::flushMemo();

        $defaults = AdminModules::defaultEnabledSlugs();
        foreach ($defaults as $slug) {
            $this->assertTrue(AdminModules::isEnabled($slug), "Expected default module {$slug} to be enabled");
        }
    }

    public function test_purchasing_module_is_defined(): void
    {
        $definitions = AdminModules::definitions();
        $this->assertArrayHasKey('purchasing', $definitions);
        $this->assertEquals('Inventory', $definitions['purchasing']['group']);
    }

    public function test_new_payout_types_present_in_streamer_labels(): void
    {
        $labels = \App\Models\Streamer::payoutTypeLabels();
        $this->assertArrayHasKey('pwe_labels', $labels);
        $this->assertArrayHasKey('hybrid', $labels);
    }
}

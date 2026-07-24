<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\AppSettings;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quick-select presets on the Workspace Modules panel — lets the owner jump
 * the enabled-module set to a known rollout stage (e.g. "Month 1 — Basics")
 * instead of hand-checking boxes. Still requires Save Changes to persist.
 */
class ModulePresetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        AdminModules::flushMemo();
    }

    protected function tearDown(): void
    {
        AdminModules::flushMemo();
        parent::tearDown();
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create(['email' => 'superadmin@test.com']);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_presets_are_valid_against_module_definitions(): void
    {
        $valid = array_keys(AdminModules::definitions());

        foreach (AdminModules::presets() as $key => $preset) {
            $this->assertArrayHasKey('label', $preset, "Preset '{$key}' missing label");
            $this->assertArrayHasKey('description', $preset, "Preset '{$key}' missing description");
            $this->assertNotEmpty($preset['slugs'], "Preset '{$key}' has no slugs");

            foreach ($preset['slugs'] as $slug) {
                $this->assertContains($slug, $valid, "Preset '{$key}' references unknown module '{$slug}'");
            }
        }
    }

    public function test_basics_preset_is_limited_to_shows_payouts_and_inventory(): void
    {
        $slugs = AdminModules::presets()['basics']['slugs'];

        $this->assertEqualsCanonicalizing(['streams', 'payouts', 'inventory'], $slugs);
    }

    public function test_owner_applying_a_preset_updates_the_unsaved_selection(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)
            ->call('applyModulePreset', 'basics')
            ->assertSet('enabled_modules', ['streams', 'payouts', 'inventory']);
    }

    public function test_applying_a_preset_does_not_persist_until_save(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)->call('applyModulePreset', 'basics');

        // Setting itself is untouched — only saveSettings() writes it.
        $this->assertNotEquals(
            json_encode(['streams', 'payouts', 'inventory']),
            \App\Models\Setting::get('enabled_admin_modules')
        );
    }

    public function test_owner_can_save_after_applying_a_preset(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)
            ->call('applyModulePreset', 'basics')
            ->call('saveSettings');

        AdminModules::flushMemo();
        $this->assertEqualsCanonicalizing(['streams', 'payouts', 'inventory'], AdminModules::enabledSlugs());
    }

    public function test_non_owner_cannot_apply_a_preset(): void
    {
        $before = AdminModules::enabledSlugs();

        $this->actingAs($this->superAdmin());

        Livewire::test(AppSettings::class)->call('applyModulePreset', 'basics');

        AdminModules::flushMemo();
        // Nothing changed — still whatever was already enabled, not the basics preset.
        $this->assertEqualsCanonicalizing($before, AdminModules::enabledSlugs());
    }

    public function test_unknown_preset_key_is_a_no_op(): void
    {
        $current = AdminModules::enabledSlugs();

        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)
            ->call('applyModulePreset', 'not_a_real_preset')
            ->assertSet('enabled_modules', $current);
    }
}

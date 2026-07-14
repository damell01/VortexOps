<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\LedgerResource;
use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proves module/feature toggles actually hide menu items for everyone,
 * including the owner — disabling a module in Settings must be reflected in
 * what the owner sees too, not just non-owner admins.
 */
class MenuHidingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@vortexbreaks.com']);
        AdminModules::flushMemo();
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $u = User::factory()->create(['email' => 'client-admin@test.com']);
        $u->assignRole('admin');

        return $u;
    }

    private function setModules(array $slugs): void
    {
        Setting::set('enabled_admin_modules', json_encode($slugs));
        AdminModules::flushMemo();
    }

    private function setFeatures(array $slugs): void
    {
        Setting::set('enabled_admin_features', json_encode($slugs));
        AdminModules::flushMemo();
    }

    // ── AdminModules: module level ─────────────────────────────────────────────

    public function test_disabling_a_module_hides_its_resource_for_a_client(): void
    {
        $this->actingAs($this->admin());

        $this->setModules(['streams', 'inventory']); // payouts OFF
        $this->assertFalse(PayoutResource::shouldRegisterNavigation(), 'Payouts should hide when its module is off');
        $this->assertTrue(ShowResource::shouldRegisterNavigation(), 'Shows stays visible (streams on)');

        $this->setModules(['streams', 'inventory', 'payouts']); // payouts ON
        $this->assertTrue(PayoutResource::shouldRegisterNavigation());
    }

    // ── AdminModules: feature level ────────────────────────────────────────────

    public function test_disabling_a_feature_hides_just_that_item(): void
    {
        $this->actingAs($this->admin());
        $this->setModules(['streams']);

        // All features except 'shows'.
        $all = array_keys(AdminModules::featureDefinitions());
        $this->setFeatures(array_values(array_filter($all, fn ($f) => $f !== 'shows')));

        $this->assertFalse(ShowResource::shouldRegisterNavigation(), 'Shows hides when its feature is off');
    }

    // ── Consolidated items (formerly the separate feature-flag system) ──────────

    public function test_consolidated_feature_hides_its_resource(): void
    {
        $this->actingAs($this->admin());
        $this->setModules(array_keys(AdminModules::definitions())); // every module on

        // Ledger now rides the "ledger" module feature, not a standalone flag.
        $all = array_keys(AdminModules::featureDefinitions());
        $this->setFeatures(array_values(array_filter($all, fn ($f) => $f !== 'ledger')));
        $this->assertFalse(LedgerResource::canAccess(), 'Ledger hides when its feature is off');

        $this->setFeatures($all);
        $this->assertTrue(LedgerResource::canAccess(), 'Ledger shows when its feature is on');
    }

    public function test_streamer_still_sees_their_log_when_feature_on(): void
    {
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $streamerUser = User::factory()->create(['email' => 'slog@test.com']);
        $streamerUser->assignRole('streamer');
        $this->actingAs($streamerUser);

        $this->setModules(array_keys(AdminModules::definitions()));
        $this->setFeatures(array_keys(AdminModules::featureDefinitions()));
        $this->assertTrue(StreamerLogResource::canAccess(), 'Streamer keeps access when the feature is on');

        // Turning the feature off hides it from the streamer too.
        $all = array_keys(AdminModules::featureDefinitions());
        $this->setFeatures(array_values(array_filter($all, fn ($f) => $f !== 'streamer_log')));
        $this->assertFalse(StreamerLogResource::canAccess());
    }

    // ── Owner also respects toggles ──────────────────────────────────────────

    public function test_owner_also_loses_access_when_toggles_are_off(): void
    {
        $owner = User::factory()->create(['email' => 'owner@vortexbreaks.com']);
        $this->actingAs($owner);

        $this->setModules([]);          // every module off
        $this->setFeatures([]);         // every feature off

        $this->assertFalse(PayoutResource::shouldRegisterNavigation());
        $this->assertFalse(ShowResource::shouldRegisterNavigation());
        $this->assertFalse(LedgerResource::canAccess());
        $this->assertFalse(StreamerLogResource::canAccess());
    }

    public function test_owner_sees_resources_when_toggles_are_on(): void
    {
        $owner = User::factory()->create(['email' => 'owner@vortexbreaks.com']);
        $this->actingAs($owner);

        $this->setModules(array_keys(AdminModules::definitions()));
        $this->setFeatures(array_keys(AdminModules::featureDefinitions()));

        $this->assertTrue(PayoutResource::shouldRegisterNavigation());
        $this->assertTrue(ShowResource::shouldRegisterNavigation());
        $this->assertTrue(LedgerResource::canAccess());
        $this->assertTrue(StreamerLogResource::canAccess());
    }
}

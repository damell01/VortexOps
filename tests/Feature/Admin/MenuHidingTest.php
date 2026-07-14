<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\LedgerResource;
use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proves the single, consolidated nav-visibility system actually hides menu
 * items for everyone, including the owner: module toggles are the coarse
 * on/off (blocks access + nav for everyone), and per-role page visibility
 * (edited on Roles & Permissions) is the only fine-grained control on top.
 */
class MenuHidingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@vortexbreaks.com']);
        AdminModules::flushMemo();
        NavVisibility::flushMemo();
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

    // ── NavVisibility: per-role fine-tuning within an enabled module ────────────

    public function test_hiding_a_page_for_a_role_hides_just_that_item(): void
    {
        $this->actingAs($this->admin());
        $this->setModules(array_keys(AdminModules::definitions()));

        NavVisibility::setHiddenForRole('admin', [ShowResource::class]);
        $this->assertFalse(ShowResource::canAccess(), 'Shows hides when hidden for the admin role');

        NavVisibility::setHiddenForRole('admin', []);
        $this->assertTrue(ShowResource::canAccess(), 'Shows is visible again once un-hidden');
    }

    public function test_hiding_ledger_for_a_role_blocks_access(): void
    {
        $this->actingAs($this->admin());
        $this->setModules(array_keys(AdminModules::definitions()));

        NavVisibility::setHiddenForRole('admin', [LedgerResource::class]);
        $this->assertFalse(LedgerResource::canAccess(), 'Ledger hides when hidden for the admin role');

        NavVisibility::setHiddenForRole('admin', []);
        $this->assertTrue(LedgerResource::canAccess(), 'Ledger shows again once un-hidden');
    }

    public function test_streamer_still_sees_their_log_when_not_hidden(): void
    {
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $streamerUser = User::factory()->create(['email' => 'slog@test.com']);
        $streamerUser->assignRole('streamer');
        $this->actingAs($streamerUser);

        $this->setModules(array_keys(AdminModules::definitions()));
        $this->assertTrue(StreamerLogResource::canAccess(), 'Streamer keeps access by default');

        // Hiding it for the streamer role hides it from the streamer too.
        NavVisibility::setHiddenForRole('streamer', [StreamerLogResource::class]);
        $this->assertFalse(StreamerLogResource::canAccess());
    }

    // ── Owner also respects module toggles, but is always immune to per-role hiding ──

    public function test_owner_also_loses_access_when_modules_are_off(): void
    {
        $owner = User::factory()->create(['email' => 'owner@vortexbreaks.com']);
        $this->actingAs($owner);

        $this->setModules([]); // every module off

        $this->assertFalse(PayoutResource::shouldRegisterNavigation());
        $this->assertFalse(ShowResource::shouldRegisterNavigation());
        $this->assertFalse(LedgerResource::canAccess());
        $this->assertFalse(StreamerLogResource::canAccess());
    }

    public function test_owner_sees_resources_when_modules_are_on(): void
    {
        $owner = User::factory()->create(['email' => 'owner@vortexbreaks.com']);
        $this->actingAs($owner);

        $this->setModules(array_keys(AdminModules::definitions()));

        $this->assertTrue(PayoutResource::shouldRegisterNavigation());
        $this->assertTrue(ShowResource::shouldRegisterNavigation());
        $this->assertTrue(LedgerResource::canAccess());
        $this->assertTrue(StreamerLogResource::canAccess());
    }

    public function test_owner_ignores_per_role_hiding(): void
    {
        $owner = User::factory()->create(['email' => 'owner@vortexbreaks.com']);
        $this->actingAs($owner);

        $this->setModules(array_keys(AdminModules::definitions()));
        NavVisibility::setHiddenForRole('admin', [LedgerResource::class, ShowResource::class]);

        $this->assertTrue(LedgerResource::canAccess(), 'Owner is immune to per-role hiding');
        $this->assertTrue(ShowResource::canAccess());
        $this->assertNotEmpty(ShowResource::getNavigationItems(), 'Owner still sees it in the sidebar');
    }
}

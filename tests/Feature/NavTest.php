<?php

namespace Tests\Feature;

use App\Filament\Resources\LedgerResource;
use App\Filament\Resources\StreamerLogResource;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        NavVisibility::flushMemo();
        config(['app.owner_email' => 'dbellcreations@gmail.com']);
        Setting::set('enabled_admin_modules', json_encode(['streams', 'inventory', 'payouts', 'streamers', 'vendors', 'purchasing', 'ledger', 'streamer_log', 'timekeeping', 'receiving']));
        AdminModules::flushMemo();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        return User::factory()->create(['email' => 'dbellcreations@gmail.com']);
    }

    private function adminUser(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');
        return $u;
    }

    public function test_app_settings_page_renders_for_owner(): void
    {
        $user = $this->ownerUser();
        Livewire::actingAs($user)
            ->test(\App\Filament\Pages\AppSettings::class)
            ->assertOk();
    }

    public function test_ledger_resource_nav_items_for_admin(): void
    {
        $user = $this->adminUser();
        \Illuminate\Support\Facades\Auth::login($user);
        $items = LedgerResource::getNavigationItems();
        $this->assertIsArray($items);
    }

    public function test_streamer_log_resource_nav_items_for_admin(): void
    {
        $user = $this->adminUser();
        \Illuminate\Support\Facades\Auth::login($user);
        $items = StreamerLogResource::getNavigationItems();
        $this->assertIsArray($items);
    }

    public function test_nav_visibility_hides_item_from_admin(): void
    {
        NavVisibility::setHiddenForAdmins([LedgerResource::class]);
        $user = $this->adminUser();
        \Illuminate\Support\Facades\Auth::login($user);
        $items = LedgerResource::getNavigationItems();
        $this->assertEmpty($items);
        NavVisibility::setHiddenForAdmins([]);
    }

    public function test_nav_visibility_still_shows_item_for_owner(): void
    {
        NavVisibility::setHiddenForAdmins([LedgerResource::class]);
        $user = $this->ownerUser();
        \Illuminate\Support\Facades\Auth::login($user);
        $items = LedgerResource::getNavigationItems();
        $this->assertNotEmpty($items);
        NavVisibility::setHiddenForAdmins([]);
    }
}

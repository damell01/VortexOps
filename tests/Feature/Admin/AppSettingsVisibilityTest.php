<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\AppSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regular admins shouldn't see AI/Ollama config or system/DB maintenance
 * controls in Settings — those stay owner + super_admin only. Branding,
 * Show Import, Notifications, and Shipping Surcharge stay visible to everyone
 * who can reach the page at all.
 */
class AppSettingsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        foreach (['admin', 'super_admin'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');

        return $u;
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create(['email' => 'superadmin@test.com']);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_regular_admin_does_not_see_ai_or_system_sections(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(AppSettings::class)
            ->assertDontSee('AI / Ollama')
            ->assertDontSee('System & Maintenance')
            ->assertSee('Branding')
            ->assertSee('Shipping Surcharge')
            ->assertSee('Notifications');
    }

    public function test_owner_sees_ai_and_system_sections(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)
            ->assertSee('AI / Ollama')
            ->assertSee('System & Maintenance');
    }

    public function test_super_admin_sees_ai_and_system_sections(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(AppSettings::class)
            ->assertSee('AI / Ollama')
            ->assertSee('System & Maintenance');
    }

    public function test_is_super_admin_checks_the_role_not_just_owner_email(): void
    {
        $this->assertTrue($this->superAdmin()->isSuperAdmin());
        $this->assertFalse($this->admin()->isSuperAdmin());
        $this->assertTrue($this->owner()->isSuperAdmin());
    }
}

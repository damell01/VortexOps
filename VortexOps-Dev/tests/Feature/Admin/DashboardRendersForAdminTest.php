<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression: the dashboard 500'd for a non-owner admin when a lazy widget's
 * Livewire update request blew up server-side. Render the page AND every
 * widget it mounts (lazy widgets only hit their getStats()/render() on the
 * follow-up Livewire request, so testing the page alone isn't enough).
 */
class DashboardRendersForAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        config(['app.owner_email' => 'dbellcreations@gmail.com']);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');
        return $u;
    }

    public function test_every_dashboard_widget_renders_for_a_non_owner_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Livewire::actingAs($admin);

        foreach ((new Dashboard)->getWidgets() as $widget) {
            if (! $widget::canView()) {
                continue;
            }
            Livewire::test($widget)->assertSuccessful();
        }
    }

    public function test_every_dashboard_widget_renders_for_the_owner(): void
    {
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($owner);
        Livewire::actingAs($owner);

        foreach ((new Dashboard)->getWidgets() as $widget) {
            if (! $widget::canView()) {
                continue;
            }
            Livewire::test($widget)->assertSuccessful();
        }
    }
}

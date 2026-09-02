<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\InventoryCatalog;
use App\Filament\Widgets\ShowWorkflowWidget;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedOperationsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['email' => 'ops-admin@test.com']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_show_workflow_is_visible_to_admin_and_maps_ended_show_to_streamer_logging(): void
    {
        $admin = $this->admin();

        Show::create([
            'title' => 'Friday Night Break',
            'show_date' => now()->subDay()->toDateString(),
            'status' => 'reconciled',
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin);

        Livewire::test(ShowWorkflowWidget::class)
            ->assertSee('Show Flow')
            ->assertSee('Friday Night Break')
            ->assertSee('Streamer Logging');
    }

    public function test_inventory_catalog_is_available_to_admin_but_not_plain_user(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->assertTrue(InventoryCatalog::canAccess());

        $plain = User::factory()->create(['email' => 'plain@test.com']);
        $this->actingAs($plain);
        $this->assertFalse(InventoryCatalog::canAccess());
    }
}

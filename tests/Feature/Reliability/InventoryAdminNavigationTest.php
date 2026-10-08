<?php
namespace Tests\Feature\Reliability;

use App\Filament\Resources\InventoryMovementResource;
use App\Filament\Resources\InventoryMovementResource\Pages\ListInventoryMovements;
use App\Filament\Resources\VendorResource;
use App\Filament\Resources\VendorResource\Pages\ListVendors;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryAdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_tools_remain_visible_to_both_admin_roles_with_existing_allow_lists(): void
    {
        foreach (['admin', 'fulfillment_admin'] as $role) {
            Role::findOrCreate($role, 'web');
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            NavVisibility::flushMemo();
            NavVisibility::setVisibleForRole('admin', []);
            foreach ([InventoryMovementResource::class, ListInventoryMovements::class, VendorResource::class, ListVendors::class] as $class) {
                $this->assertFalse(NavVisibility::isHiddenForUser($class, $user));
                $this->assertTrue($class::canAccess(), $role . ': ' . $class);
            }
            $this->assertTrue(InventoryMovementResource::shouldRegisterNavigation());
            $this->assertTrue(VendorResource::shouldRegisterNavigation());
            $this->assertSame(InventoryMovementResource::getNavigationGroup(), VendorResource::getNavigationGroup());
        }
    }
}

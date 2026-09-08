<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryCatalogAddStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'inventory-admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
    }

    public function test_catalog_add_stock_opener_mounts_the_registered_add_stock_action(): void
    {
        $product = Product::create([
            'name' => 'Catalog Add Stock Test Item',
            'is_active' => true,
        ]);

        Livewire::test(ListInventoryItems::class)
            ->call('openQuickAddStock', $product->getKey())
            ->assertSet('selectedStockProductId', $product->getKey())
            ->assertSet('quickStockScanTargetId', $product->getKey())
            ->assertActionMounted('add_stock');
    }
}

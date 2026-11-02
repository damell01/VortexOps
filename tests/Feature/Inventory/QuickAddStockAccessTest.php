<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryOverview;
use App\Filament\Pages\InventoryScanner;
use App\Filament\Pages\QuickAddStock;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Tools tab's Quick Add Stock button. It opens its own page, so a custom
 * role granted the inventory pages and the scanner — but not that page by
 * name, because it was new — got a 403 from the button.
 */
class QuickAddStockAccessTest extends TestCase
{
    use RefreshDatabase;

    private function warehouseUser(array $granted): User
    {
        $this->enableAdminModules();
        Role::findOrCreate('warehouse', 'web');
        $user = User::factory()->create(['email' => 'warehouse@example.com']);
        $user->assignRole('warehouse');
        NavVisibility::setVisibleForRole('warehouse', $granted);
        NavVisibility::flushMemo();

        return $user->fresh();
    }

    public function test_a_role_with_the_scanner_can_use_the_tools_tab_button(): void
    {
        $this->actingAs($this->warehouseUser([\App\Filament\Resources\InventoryItemResource::class, InventoryOverview::class, InventoryScanner::class]));
        $main = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
        $item = InventoryItem::create(['name' => 'Hobby Box', 'sku' => 'HB-1', 'barcode' => '0001', 'unit_cost' => 10, 'is_active' => true]);

        $this->get(InventoryOverview::getUrl(['tab' => 'tools']))->assertOk()->assertSee(QuickAddStock::getUrl(), false);
        // The old Quick Add address opens the scanner for anyone who has it
        // (44ea3e3e), where scan → quantity → Confirm Add Stock books it.
        $this->get(QuickAddStock::getUrl())->assertRedirect(InventoryScanner::getUrl());

        Livewire::test(InventoryScanner::class)
            ->set('scanInput', '0001')
            ->call('submitScan')
            ->set('qaLocationId', $main->id)
            ->set('qaQty', '4')
            ->call('addSelectedStock');

        $this->assertSame(4.0, (float) InventoryStock::where('inventory_item_id', $item->id)->value('quantity'));
    }

    public function test_a_role_with_neither_stays_out(): void
    {
        $this->actingAs($this->warehouseUser([\App\Filament\Resources\InventoryItemResource::class, InventoryOverview::class]));

        $this->get(QuickAddStock::getUrl())->assertForbidden();
        // No dead button on the Tools tab either.
        $this->get(InventoryOverview::getUrl(['tab' => 'tools']))->assertOk()->assertDontSee(InventoryScanner::getUrl(), false)->assertDontSee(QuickAddStock::getUrl(), false);
    }
}

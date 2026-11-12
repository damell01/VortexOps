<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** A deleted item never keeps stock: it is written off with a log line, however it was deleted. */
class DeletedItemsLeaveNoStockTest extends TestCase
{
    use RefreshDatabase;

    private function stocked(): array
    {
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $main = InventoryLocation::create(['name' => 'Main Warehouse', 'type' => 'main_storage', 'status' => 'active']);
        $item = InventoryItem::create(['name' => 'Hobby Box', 'sku' => 'HB-1', 'unit_cost' => 10, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $main->id, 'quantity' => 5]);

        return [$item, $main];
    }

    public function test_any_delete_writes_the_stock_off_with_a_log_line(): void
    {
        [$item, $main] = $this->stocked();

        $item->delete(); // not through the delete button: a bulk action, merge or script

        $this->assertSame(0.0, (float) DB::table('inventory_stock')->where('inventory_item_id', $item->id)->sum('quantity'));
        $movement = InventoryMovement::query()->where('inventory_item_id', $item->id)->latest('id')->first();
        $this->assertSame(5.0, abs((float) $movement->quantity));
        $this->assertSame($main->id, (int) $movement->from_location_id);
        $this->assertStringContainsString('item deleted', (string) $movement->reason);
        $this->assertTrue($item->fresh()->trashed());
    }

    public function test_leftover_stock_on_items_deleted_earlier_is_written_off_by_the_command(): void
    {
        [$item] = $this->stocked();
        DB::table('products')->where('id', $item->id)->update(['deleted_at' => now()]); // deleted the old way

        $this->artisan('inventory:write-off-deleted')->expectsOutputToContain('Run with --apply')->assertSuccessful();
        $this->assertSame(5.0, (float) DB::table('inventory_stock')->where('inventory_item_id', $item->id)->sum('quantity'));

        $this->artisan('inventory:write-off-deleted --apply')->assertSuccessful();
        $this->assertSame(0.0, (float) DB::table('inventory_stock')->where('inventory_item_id', $item->id)->sum('quantity'));
        $this->assertStringContainsString('leftover stock of a deleted item', (string) InventoryMovement::query()->where('inventory_item_id', $item->id)->latest('id')->value('reason'));
        $this->assertNotNull(DB::table('products')->where('id', $item->id)->value('deleted_at'));
    }
}

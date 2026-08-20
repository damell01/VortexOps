<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting an item should take it out of the counts.
 *
 * Products are soft-deleted so their movement history survives — that part is
 * deliberate. But the stock rows went on being counted, so an item deleted from
 * inventory kept appearing under a location's totals, which reads as the
 * deletion having silently failed.
 */
class DeletedItemStockTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = InventoryLocation::create([
            'name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active',
        ]);
    }

    private function stockedItem(string $name, float $quantity = 5): InventoryItem
    {
        $item = InventoryItem::create([
            'name' => $name, 'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => $quantity,
        ]);

        return $item;
    }

    public function test_a_live_item_counts(): void
    {
        $this->stockedItem('Still Here');

        $this->assertSame(1, $this->location->stock()->count());
    }

    public function test_a_deleted_item_stops_counting(): void
    {
        $item = $this->stockedItem('Deleted Later');
        $this->stockedItem('Still Here');

        $item->delete();

        $this->assertSame(1, $this->location->fresh()->stock()->count());
    }

    public function test_the_stock_row_is_kept_rather_than_destroyed(): void
    {
        // The movement history behind it is the record of what happened, so the
        // row survives the item — it just stops being counted.
        $item = $this->stockedItem('Deleted Later');
        $item->delete();

        $this->assertSame(1, $this->location->fresh()->allStock()->count());
    }

    public function test_restoring_an_item_brings_its_stock_back(): void
    {
        // whereHas applies the product's own soft-delete scope, so a restore
        // needs nothing else written to be reflected.
        $item = $this->stockedItem('Back Again');
        $item->delete();

        $this->assertSame(0, $this->location->fresh()->stock()->count());

        $item->restore();

        $this->assertSame(1, $this->location->fresh()->stock()->count());
    }

    public function test_counts_loaded_eagerly_agree_with_the_relation(): void
    {
        // withCount goes through the same relation, so a list showing totals
        // cannot disagree with the location page.
        $this->stockedItem('Kept');
        $this->stockedItem('Gone')->delete();

        $this->assertSame(
            1,
            (int) InventoryLocation::withCount('stock')->find($this->location->id)->stock_count,
        );
    }
}

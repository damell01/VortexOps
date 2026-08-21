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

    // ── Everywhere else it used to keep showing up ────────────────────────

    public function test_it_disappears_from_every_stock_query_at_once(): void
    {
        // The scope is on the model rather than on each screen, so this is one
        // assertion standing in for two dozen call sites — including the ones
        // written after this test.
        $item = $this->stockedItem('Gone');
        $this->stockedItem('Still Here');

        $this->assertSame(2, InventoryStock::count());

        $item->delete();

        $this->assertSame(1, InventoryStock::count(), 'A deleted product still had a stock row in scope.');
        $this->assertSame(0, InventoryStock::where('inventory_item_id', $item->id)->count());
    }

    public function test_the_row_is_still_there_for_anything_that_asks_for_it(): void
    {
        // Hidden, not destroyed. A restore has to have something to bring back.
        $item = $this->stockedItem('Gone');
        $item->delete();

        $this->assertSame(
            1,
            InventoryStock::withoutGlobalScope(InventoryStock::LIVE_PRODUCT_SCOPE)
                ->where('inventory_item_id', $item->id)
                ->count(),
        );
    }

    public function test_totals_do_not_count_it(): void
    {
        // The complaint that started this: a location's totals still included
        // items that had been deleted, so the numbers on the locations screen
        // disagreed with the list underneath them.
        $this->stockedItem('Kept', 5);
        $gone = $this->stockedItem('Gone', 100);

        $gone->delete();

        $this->assertEqualsWithDelta(5.0, (float) InventoryStock::sum('quantity'), 0.001);
    }

    public function test_a_restore_brings_it_back_everywhere(): void
    {
        // whereHas runs the product's own soft-delete scope, so nothing has to
        // be written to undo this — which is what makes one scope safe to trust
        // in two dozen places.
        $item = $this->stockedItem('Back');
        $item->delete();

        $this->assertSame(0, InventoryStock::count());

        $item->restore();

        $this->assertSame(1, InventoryStock::count());
    }

    public function test_the_movements_widget_does_not_name_a_deleted_item(): void
    {
        // Its rows survive on purpose — they are the record of what happened —
        // but a dashboard listing a deleted item by name reads as the deletion
        // having failed.
        $item = $this->stockedItem('Deleted Later');

        \App\Models\InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'to_location_id'    => $this->location->id,
            'quantity'          => 5,
            'movement_type'     => 'opening',
        ]);

        $this->assertSame(1, \App\Models\InventoryMovement::whereHas('item')->count());

        $item->delete();

        $this->assertSame(0, \App\Models\InventoryMovement::whereHas('item')->count());
        $this->assertSame(1, \App\Models\InventoryMovement::count(), 'The history itself must survive.');
    }
}

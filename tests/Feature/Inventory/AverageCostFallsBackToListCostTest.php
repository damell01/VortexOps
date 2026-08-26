<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What an item is worth before anything has been received.
 *
 * average_cost is a weighted average of receipts, so it is genuinely 0.0000
 * until a pallet lands — but an item imported from a cost sheet has a perfectly
 * good list cost sitting right next to it. Showing $0.00 reads as "this stock is
 * worth nothing", not as "nothing has been received yet", and the same zero was
 * being multiplied into snapshot valuations and package values.
 *
 * The average takes over the moment there is one. Until then the list cost is
 * the honest answer.
 */
class AverageCostFallsBackToListCostTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $attributes = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'name'      => 'Topps Chrome Hobby Box',
            'sku'       => 'TC-HB',
            'unit_cost' => 89.50,
            'is_active' => true,
        ], $attributes));
    }

    public function test_an_item_with_no_receipts_is_worth_its_list_cost(): void
    {
        $this->assertSame(89.50, $this->item()->effectiveCost());
    }

    public function test_a_received_average_takes_over_from_the_list_cost(): void
    {
        $item = $this->item(['average_cost' => 94.25]);

        $this->assertSame(94.25, $item->effectiveCost());
    }

    public function test_an_item_with_neither_is_worth_nothing_rather_than_erroring(): void
    {
        $item = $this->item(['unit_cost' => 0]);

        $this->assertSame(0.0, $item->effectiveCost());
    }

    public function test_a_snapshot_values_unreceived_stock_at_the_list_cost(): void
    {
        // This was the quiet half: the table showing $0.00 is visible, a
        // snapshot understating the whole warehouse is not.
        $item     = $this->item();
        $location = InventoryLocation::create(['name' => 'Main Shelf', 'is_active' => true]);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $location->id,
            'quantity'              => 4,
        ]);

        $value = InventoryStock::with('item')->get()
            ->sum(fn ($stock) => $stock->quantity * ($stock->item?->effectiveCost() ?? 0));

        $this->assertSame(358.0, (float) $value);
    }

    public function test_the_products_table_and_the_items_table_agree(): void
    {
        // InventoryItem extends Product, so a divergence here would mean two
        // parts of the app valuing the same row differently.
        $product = Product::create([
            'name'      => 'Prizm Blaster',
            'sku'       => 'PZ-BL',
            'unit_cost' => 24.99,
            'is_active' => true,
        ]);

        $this->assertSame(
            $product->effectiveCost(),
            InventoryItem::find($product->id)->effectiveCost(),
        );
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySnapshot;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The inventory report has to cost the same at five thousand products as at
 * forty.
 *
 * It did not. Building the snapshot asked "has this item moved in 30 days?"
 * per stock row — a count(*) over inventory_movements each time — so the page
 * ran 57 queries over 40 products, 97 over 80, one more for every product
 * added. A catalogue the size of a real warehouse meant thousands of round
 * trips to draw one page, and it would have got worse every week without
 * anything visibly changing.
 *
 * Counting queries is a blunt test, but the failure it guards is exactly the
 * one that does not show up in a small fixture and is agony in production.
 */
class InventoryReportScalesTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->location = InventoryLocation::create([
            'name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active',
        ]);
    }

    private int $created = 0;

    private function stockedItems(int $count): void
    {
        foreach (range(1, $count) as $ignored) {
            $i = ++$this->created;

            $item = InventoryItem::create([
                'name' => "Item {$i}", 'sku' => "SCALE-{$i}",
                'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
            ]);

            InventoryStock::create([
                'inventory_item_id'     => $item->id,
                'inventory_location_id' => $this->location->id,
                'quantity'              => 5,
            ]);
        }
    }

    private function queriesToBuildSnapshot(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        InventorySnapshot::generateCurrent();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_cost_does_not_grow_with_the_catalogue(): void
    {
        $this->stockedItems(10);
        $small = $this->queriesToBuildSnapshot();

        $this->stockedItems(40);
        $larger = $this->queriesToBuildSnapshot();

        $this->assertSame(
            $small,
            $larger,
            "building the snapshot cost {$small} queries for 10 products and {$larger} for 50 — it is scaling with the catalogue",
        );
    }

    public function test_an_item_that_has_not_moved_is_reported_as_slow_moving(): void
    {
        // The behaviour the batched lookup replaced, which is the thing worth
        // protecting — a faster page that stopped answering the question would
        // be a worse outcome than a slow one.
        $this->stockedItems(1);

        $snapshot = InventorySnapshot::generateCurrent();

        $this->assertCount(1, $snapshot->slow_moving_items ?? []);
    }

    public function test_an_item_that_moved_recently_is_not_slow_moving(): void
    {
        $this->stockedItems(1);
        $item = InventoryItem::first();

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'to_location_id'    => $this->location->id,
            'quantity'          => 3,
            'quantity_before'   => 2,
            'quantity_after'    => 5,
            'movement_type'     => 'receipt',
        ]);

        $snapshot = InventorySnapshot::generateCurrent();

        $this->assertSame([], $snapshot->slow_moving_items ?? []);
    }

    public function test_a_movement_older_than_the_window_does_not_count_as_recent(): void
    {
        $this->stockedItems(1);
        $item = InventoryItem::first();

        $movement = InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'to_location_id'    => $this->location->id,
            'quantity'          => 3,
            'quantity_before'   => 2,
            'quantity_after'    => 5,
            'movement_type'     => 'receipt',
        ]);

        $movement->forceFill(['created_at' => now()->subDays(45)])->saveQuietly();

        $snapshot = InventorySnapshot::generateCurrent();

        $this->assertCount(1, $snapshot->slow_moving_items ?? []);
    }
}

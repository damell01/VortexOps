<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reduction has to read as a reduction.
 *
 * Movements store `quantity` as an absolute value and put the direction in
 * whichever of from_location_id / to_location_id is set. That is workable for a
 * transfer, where both are real places, and wrong for an adjustment, where the
 * direction is the entire content of the record. Every surface reading the
 * column on its own showed a removal as a gain — the scanner's history printed
 * "+" against each row because it tested `qty > 0` against a number that is
 * never negative.
 *
 * The fix is to stop inferring. The quantity before and after are known at the
 * moment of the write, so they are stored, and the change is arithmetic:
 * after minus before. These tests hold the arithmetic, and hold the fallback
 * for the rows written before the columns existed.
 */
class MovementDirectionTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $item;
    private InventoryLocation $main;
    private InventoryLocation $shelf;
    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->service = app(InventoryService::class);

        $this->main  = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $this->shelf = InventoryLocation::create(['name' => 'Shelf', 'type' => 'main_storage', 'status' => 'active']);

        $this->item = InventoryItem::create([
            'name' => 'Chrome Box', 'sku' => 'CHR-1', 'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
        ]);
    }

    private function stockAt(InventoryLocation $location, float $quantity): void
    {
        InventoryStock::updateOrCreate(
            ['inventory_item_id' => $this->item->id, 'inventory_location_id' => $location->id],
            ['quantity' => $quantity],
        );
    }

    private function quantityAt(InventoryLocation $location): float
    {
        return (float) InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('inventory_location_id', $location->id)
            ->value('quantity');
    }

    // ── The three cases named in the report ──────────────────────────────────

    public function test_adding_stock_records_a_gain(): void
    {
        $this->stockAt($this->main, 10);

        $movement = $this->service->adjustStock($this->item, $this->main, 15, 'count');

        $this->assertSame(15.0, $this->quantityAt($this->main));
        $this->assertSame(5.0, $movement->signedChange());
        $this->assertSame('+5', $movement->changeLabel());
    }

    public function test_removing_stock_records_a_loss(): void
    {
        // The reported bug: this came back as a positive adjustment.
        $this->stockAt($this->main, 15);

        $movement = $this->service->adjustStock($this->item, $this->main, 12, 'shrinkage');

        $this->assertSame(12.0, $this->quantityAt($this->main));
        $this->assertSame(-3.0, $movement->signedChange());
        $this->assertSame('-3', $movement->changeLabel());
        $this->assertTrue($movement->isDecrease());
    }

    public function test_setting_an_exact_quantity_records_the_difference(): void
    {
        $this->stockAt($this->main, 12);

        $movement = $this->service->adjustStock($this->item, $this->main, 7, 'recount');

        $this->assertSame(-5.0, $movement->signedChange());
        $this->assertSame('-5', $movement->changeLabel());
    }

    public function test_zeroing_stock_records_the_whole_amount(): void
    {
        $this->stockAt($this->main, 12);

        $this->assertSame(-12.0, $this->service->adjustStock($this->item, $this->main, 0, 'written off')->signedChange());
    }

    // ── What the history now holds ───────────────────────────────────────────

    public function test_the_levels_either_side_are_recorded(): void
    {
        // The point of storing them: the log says what the stock was, not only
        // what moved, so a disagreement can be traced to the movement that
        // caused it rather than inferred from the ones around it.
        $this->stockAt($this->main, 20);

        $movement = $this->service->adjustStock($this->item, $this->main, 8, 'count');

        $this->assertSame(20.0, (float) $movement->quantity_before);
        $this->assertSame(8.0, (float) $movement->quantity_after);
    }

    public function test_adding_stock_through_the_service_records_them_too(): void
    {
        $this->stockAt($this->main, 4);

        $movement = $this->service->addStock($this->item, $this->main, 6, 'opening', 'delivery');

        $this->assertSame(4.0, (float) $movement->quantity_before);
        $this->assertSame(10.0, (float) $movement->quantity_after);
        $this->assertSame(6.0, $movement->signedChange());
    }

    // ── Transfers ────────────────────────────────────────────────────────────

    public function test_a_transfer_moves_stock_without_creating_any(): void
    {
        $this->stockAt($this->main, 20);

        $this->service->transferStock($this->item, $this->main, $this->shelf, 5, 'restock');

        $this->assertSame(15.0, $this->quantityAt($this->main));
        $this->assertSame(5.0, $this->quantityAt($this->shelf));
    }

    public function test_a_transfer_records_the_source_levels(): void
    {
        $this->stockAt($this->main, 20);

        $movement = $this->service->transferStock($this->item, $this->main, $this->shelf, 5, 'restock');

        $this->assertSame(20.0, (float) $movement->quantity_before);
        $this->assertSame(15.0, (float) $movement->quantity_after);
        $this->assertSame($this->main->id, $movement->from_location_id);
        $this->assertSame($this->shelf->id, $movement->to_location_id);
    }

    public function test_a_transfer_that_cannot_complete_moves_nothing(): void
    {
        // Either both sides happen or neither does. Deducting the source and
        // failing before crediting the destination destroys stock.
        $this->stockAt($this->main, 3);

        try {
            $this->service->transferStock($this->item, $this->main, $this->shelf, 10, 'too much');
            $this->fail('Transferring more than exists should have been refused.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(3.0, $this->quantityAt($this->main));
        $this->assertSame(0.0, $this->quantityAt($this->shelf));
        $this->assertSame(0, InventoryMovement::where('movement_type', 'transfer')->count());
    }

    // ── Rows written before the columns existed ──────────────────────────────

    public function test_an_old_outward_row_still_reads_as_a_loss(): void
    {
        // History predating this has no before/after, so it falls back to the
        // direction in the location columns. Stock leaving is negative.
        $movement = InventoryMovement::create([
            'inventory_item_id' => $this->item->id,
            'from_location_id'  => $this->main->id,
            'to_location_id'    => null,
            'quantity'          => 4,
            'movement_type'     => 'adjustment',
        ]);

        $this->assertSame(-4.0, $movement->signedChange());
        $this->assertSame('-4', $movement->changeLabel());
    }

    public function test_an_old_inward_row_still_reads_as_a_gain(): void
    {
        $movement = InventoryMovement::create([
            'inventory_item_id' => $this->item->id,
            'from_location_id'  => null,
            'to_location_id'    => $this->main->id,
            'quantity'          => 4,
            'movement_type'     => 'adjustment',
        ]);

        $this->assertSame(4.0, $movement->signedChange());
        $this->assertSame('+4', $movement->changeLabel());
    }

    public function test_an_old_transfer_is_not_given_a_sign_it_does_not_have(): void
    {
        // A transfer removes from one place and adds to another; across the
        // item it nets to zero. Printing it as "+5" or "-5" would be a claim
        // about a location the row is not being shown against.
        $movement = InventoryMovement::create([
            'inventory_item_id' => $this->item->id,
            'from_location_id'  => $this->main->id,
            'to_location_id'    => $this->shelf->id,
            'quantity'          => 5,
            'movement_type'     => 'transfer',
        ]);

        $this->assertSame('5', $movement->changeLabel());
    }
}

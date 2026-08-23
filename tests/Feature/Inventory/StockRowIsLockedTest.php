<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stock reads that decide a write have to hold the row.
 *
 * adjustStock stores an absolute quantity rather than a delta, which makes a
 * stale read destructive rather than merely wrong: 20 on hand, a scan adds 10,
 * and a count submitted as 14 against the stale 20 stores 14 — the ten units
 * are gone from the record with nothing saying they ever arrived. Every method
 * that reduces stock already took the lock; the two that raised it did not.
 *
 * The race itself cannot be run here: the suite is SQLite, which serialises
 * writes and drops the lock clause from the SQL entirely. So these check what
 * is checkable in-process — that both methods still take their row through the
 * locking helper, and that the before/after written onto the movement describe
 * the row that was actually stored.
 */
class StockRowIsLockedTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $item;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->location = InventoryLocation::create([
            'name' => 'Main', 'type' => 'main_storage', 'status' => 'active',
        ]);

        $this->item = InventoryItem::create([
            'name' => 'Chrome Box', 'sku' => 'LOCK-1',
            'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => 20,
        ]);
    }

    /**
     * A structural check, and worth saying so rather than dressing it up.
     *
     * Sniffing the executed SQL for "for update" was the first attempt and it
     * cannot work: SQLite's grammar drops the clause entirely, so the string
     * is absent whether or not the code asks for it. Running the race needs
     * two connections against MySQL, which the suite does not have.
     *
     * So this asserts the one thing that is checkable in-process — that both
     * methods still take their row through the locking helper. It would not
     * notice a lock that stopped working; it does notice the lock being
     * dropped, which is how this went missing the first time.
     */
    private function serviceSource(): string
    {
        return file_get_contents((new \ReflectionClass(InventoryService::class))->getFileName());
    }

    public function test_the_helper_that_reads_a_stock_row_holds_it(): void
    {
        $source = $this->serviceSource();

        $helper = substr($source, strpos($source, 'private function lockedStock'));
        $helper = substr($helper, 0, strpos($helper, "\n    }") + 6);

        $this->assertStringContainsString('lockForUpdate()', $helper);
    }

    public function test_both_raising_methods_go_through_that_helper(): void
    {
        $source = $this->serviceSource();

        foreach (['addStock', 'adjustStock'] as $method) {
            $body = substr($source, strpos($source, "public function {$method}("));
            $body = substr($body, 0, strpos($body, "\n    }") + 6);

            $this->assertStringContainsString(
                'lockedStock(', $body,
                "{$method} reads the stock row without taking the lock",
            );
            $this->assertStringNotContainsString(
                'InventoryStock::firstOrCreate(', $body,
                "{$method} still creates its own unlocked stock row",
            );
        }
    }

    public function test_the_movement_describes_the_row_that_was_stored(): void
    {
        $movement = app(InventoryService::class)->adjustStock(
            $this->item, $this->location, 14, 'counted',
        );

        $stored = InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('inventory_location_id', $this->location->id)
            ->value('quantity');

        $this->assertEqualsWithDelta(20.0, (float) $movement->quantity_before, 0.01);
        $this->assertEqualsWithDelta(14.0, (float) $movement->quantity_after, 0.01);
        $this->assertEqualsWithDelta((float) $stored, (float) $movement->quantity_after, 0.01);
    }

    public function test_a_first_ever_receipt_still_works_when_no_row_exists(): void
    {
        // The lock is taken on a row that has to be created first, so the path
        // where there is nothing to lock yet is the one most likely to break.
        $fresh = InventoryLocation::create([
            'name' => 'Back Room', 'type' => 'main_storage', 'status' => 'active',
        ]);

        $movement = app(InventoryService::class)->addStock($this->item, $fresh, 7, 'opening');

        $this->assertEqualsWithDelta(0.0, (float) $movement->quantity_before, 0.01);
        $this->assertEqualsWithDelta(7.0, (float) $movement->quantity_after, 0.01);
        $this->assertEqualsWithDelta(7.0, (float) InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('inventory_location_id', $fresh->id)->value('quantity'), 0.01);
    }
}

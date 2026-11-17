<?php

namespace Tests\Feature\Inventory;

use App\Console\Commands\SyncInventoryLots;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryStock;
use App\Services\InventoryCostService;
use App\Services\InventoryLotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Average cost is the average of what is on hand, never of everything ever received. */
class OnHandAverageCostTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $shelf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shelf = InventoryLocation::create(['name' => 'Main Warehouse', 'type' => 'main_storage', 'status' => 'active']);
    }

    private function item(): InventoryItem
    {
        return InventoryItem::create(['name' => 'Hobby box', 'unit_cost' => 80, 'sale_price' => 150, 'is_active' => true]);
    }

    private function wave(InventoryItem $item, float $qty, float $cost, string $when): InventoryLot
    {
        return InventoryLot::create(['product_id' => $item->id, 'quantity' => $qty, 'remaining_quantity' => $qty, 'unit_cost' => $cost,
            'status' => InventoryLot::STATUS_ACTIVE, 'source' => InventoryLot::SOURCE_RECEIVED, 'received_at' => $when]);
    }

    private function onHand(InventoryItem $item, float $qty): void
    {
        InventoryStock::updateOrCreate(['inventory_item_id' => $item->id, 'inventory_location_id' => $this->shelf->id], ['quantity' => $qty]);
    }

    public function test_two_waves_on_hand_average_and_a_sold_out_wave_drops_out(): void
    {
        $lots = app(InventoryLotService::class);
        $item = $this->item();
        $this->wave($item, 10, 100, '2026-11-01');
        $this->wave($item, 10, 50, '2026-11-10');
        $lots->recompute($item);
        $this->assertEquals(75.0, (float) $item->fresh()->average_cost, '$100 and $50 waves on hand average $75');

        $lots->consume($item, 10); // the first wave sells out
        $this->assertEquals(50.0, (float) $item->fresh()->average_cost, 'only the $50 wave is on hand');

        $breakdown = app(InventoryCostService::class)->getCostBreakdown($item->fresh());
        $this->assertEquals([50.0], array_map('floatval', array_column(array_merge(...array_column($breakdown, 'receipts')), 'unit_cost')), 'the sold-out wave is not in the breakdown');
        $this->assertEquals(10.0, array_sum(array_column($breakdown, 'total_qty')));
    }

    public function test_sync_lots_uses_up_stock_the_shelves_no_longer_hold_oldest_first(): void
    {
        $item = $this->item();
        $this->wave($item, 10, 100, '2026-11-01');
        $this->wave($item, 10, 50, '2026-11-10');
        $item->forceFill(['average_cost' => 75])->save();
        $this->onHand($item, 10); // the first wave was sold before lot tracking caught it

        $plan = SyncInventoryLots::plan();
        $this->assertCount(1, $plan);
        $this->assertEquals(10.0, $plan[0]['excess']);
        $this->assertEquals(50.0, $plan[0]['avg_after']);
        $this->assertEquals(75.0, (float) $item->fresh()->average_cost, 'the plan changes nothing');

        $this->artisan('inventory:sync-lots', ['--apply' => true])->assertSuccessful();
        $this->assertEquals(50.0, (float) $item->fresh()->average_cost);
        $this->assertEquals(10.0, (float) InventoryLot::where('product_id', $item->id)->sum('remaining_quantity'));
        $this->assertEquals(10.0, (float) InventoryStock::where('inventory_item_id', $item->id)->sum('quantity'), 'stock counts are never touched');
        $this->assertSame([], SyncInventoryLots::plan(), 'running it again changes nothing');
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PalletArchiveService;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Archiving (deleting) a pallet undoes its contribution to cost, and
 * restoring it puts that contribution straight back — without disturbing
 * whatever other pallets already gave the same product.
 */
class PalletArchiveServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;
    private InventoryItem $item;
    private PalletArchiveService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $this->item      = InventoryItem::create([
            'name' => 'Chrome Box', 'sku' => 'CHR-2', 'average_cost' => 0, 'is_active' => true,
        ]);
        $this->service = app(PalletArchiveService::class);
    }

    /** Receives a fully-mapped line for the shared item, on its own pallet. */
    private function receivePallet(string $name, float $unitCost, int $cases, float $perCase): Pallet
    {
        $pallet = Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'V-' . $name, 'status' => 'active'])->id,
            'name'      => $name,
            'status'    => 'receiving',
        ]);

        $line = $pallet->lines()->create([
            'line_number'           => 1,
            'description'           => 'Chrome Box',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => $cases,
            'quantity_per_case'     => $perCase,
            'unit_cost'             => $unitCost,
        ]);

        $receiving = app(ReceivingService::class);
        $receiving->generateExpectedCases($line->refresh());

        foreach ($line->cases as $case) {
            $receiving->receiveCase($case);
        }

        $pallet->update(['status' => 'received']);

        return $pallet->fresh();
    }

    public function test_archiving_releases_only_that_pallets_lots_and_recomputes(): void
    {
        $this->receivePallet('A', unitCost: 100, cases: 2, perCase: 5); // 10 @ 100
        $palletB = $this->receivePallet('B', unitCost: 200, cases: 1, perCase: 5); // 5 @ 200

        // Blended: (10*100 + 5*200) / 15 = 133.33
        $this->assertEqualsWithDelta(133.3333, (float) $this->item->fresh()->average_cost, 0.01);
        $this->assertEquals(15, (float) InventoryStock::where('inventory_item_id', $this->item->id)->first()->quantity);

        $this->service->archive($palletB);

        // Pallet B's lot is gone; only A's 10 @ 100 remains.
        $this->assertEquals(10, (float) InventoryStock::where('inventory_item_id', $this->item->id)->first()->quantity);
        $this->assertEqualsWithDelta(100.0, (float) $this->item->fresh()->average_cost, 0.01);
        $this->assertSame(0, InventoryLot::where('pallet_line_id', $palletB->lines->first()->id)->count());
    }

    public function test_restoring_an_archived_pallet_recreates_its_lot_and_average(): void
    {
        $this->receivePallet('A', unitCost: 100, cases: 2, perCase: 5); // 10 @ 100
        $palletB = $this->receivePallet('B', unitCost: 200, cases: 1, perCase: 5); // 5 @ 200

        $this->service->archive($palletB);
        $this->assertEqualsWithDelta(100.0, (float) $this->item->fresh()->average_cost, 0.01);

        $this->service->restore($palletB->fresh());

        $this->assertEquals(15, (float) InventoryStock::where('inventory_item_id', $this->item->id)->first()->quantity);
        $this->assertEqualsWithDelta(133.3333, (float) $this->item->fresh()->average_cost, 0.01);
    }
}

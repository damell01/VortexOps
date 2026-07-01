<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryCase;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReceivingService $service;
    private User $user;
    private InventoryItem $item;
    private InventoryLocation $location;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service  = app(ReceivingService::class);
        $this->user     = User::factory()->create();
        $this->actingAs($this->user);

        $this->vendor   = Vendor::create(['name' => 'Test Vendor', 'status' => 'active']);
        $this->location = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
        $this->item     = InventoryItem::create([
            'name'         => 'Test Cards',
            'unit_cost'    => 5.00,
            'average_cost' => 0,
            'is_active'    => true,
        ]);
    }

    public function test_average_cost_calculates_correctly_on_first_receipt(): void
    {
        $this->service->recalculateAverageCost($this->item, 100, 5.00);

        $this->item->refresh();
        $this->assertEquals(5.0000, (float) $this->item->average_cost);
        $this->assertEquals(100, (float) $this->item->total_units_received);
    }

    public function test_average_cost_uses_weighted_average_across_receipts(): void
    {
        // First receipt: 100 units @ $5
        $this->service->recalculateAverageCost($this->item, 100, 5.00);
        // Second receipt: 100 units @ $7
        $this->service->recalculateAverageCost($this->item->fresh(), 100, 7.00);

        $this->item->refresh();
        // Expected: (100*5 + 100*7) / 200 = 6.00
        $this->assertEquals(6.0000, (float) $this->item->average_cost);
        $this->assertEquals(200, (float) $this->item->total_units_received);
    }

    public function test_average_cost_skips_zero_cost_receipts(): void
    {
        $this->service->recalculateAverageCost($this->item, 50, 4.00);
        $this->service->recalculateAverageCost($this->item->fresh(), 10, 0);

        $this->item->refresh();
        // Cost should remain 4.00, only units_received increments
        $this->assertEquals(4.0000, (float) $this->item->average_cost);
        $this->assertEquals(60, (float) $this->item->total_units_received);
    }

    public function test_receive_case_credits_stock_and_logs_movement(): void
    {
        $pallet = Pallet::create([
            'vendor_id'     => $this->vendor->id,
            'reference'     => 'PO-001',
            'status'        => 'pending',
            'created_by'    => $this->user->id,
        ]);

        $line = PalletLine::create([
            'pallet_id'             => $pallet->id,
            'line_number'           => 1,
            'description'           => 'Test Cards Box',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 1,
            'quantity_per_case'     => 12,
            'unit_cost'             => 6.00,
        ]);

        $case = InventoryCase::create([
            'pallet_line_id' => $line->id,
            'barcode'        => 'CASE-001',
            'status'         => 'expected',
        ]);

        $this->service->receiveCase($case);

        // Stock credited
        $stock = InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('inventory_location_id', $this->location->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(12, (float) $stock->quantity);

        // Movement logged
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $this->item->id,
            'to_location_id'    => $this->location->id,
            'quantity'          => 12,
            'movement_type'     => 'opening',
        ]);

        // Average cost updated
        $this->item->refresh();
        $this->assertEquals(6.0000, (float) $this->item->average_cost);

        // Case marked received
        $case->refresh();
        $this->assertEquals('received', $case->status);
        $this->assertEquals(12, (float) $case->quantity_received);
    }

    public function test_receive_case_by_barcode_fails_on_unknown_barcode(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No case found/');

        $this->service->receiveCaseByBarcode('BARCODE-UNKNOWN');
    }

    public function test_receive_case_by_barcode_fails_on_already_received(): void
    {
        $pallet = Pallet::create(['vendor_id' => $this->vendor->id, 'status' => 'receiving', 'created_by' => $this->user->id]);
        $line   = PalletLine::create([
            'pallet_id'             => $pallet->id,
            'line_number'           => 1,
            'description'           => 'Cards',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 1,
            'quantity_per_case'     => 6,
            'unit_cost'             => 5.00,
        ]);
        InventoryCase::create(['pallet_line_id' => $line->id, 'barcode' => 'DONE-001', 'status' => 'received']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already been received/');

        $this->service->receiveCaseByBarcode('DONE-001');
    }

    public function test_receive_all_cases_for_line_auto_creates_cases(): void
    {
        $pallet = Pallet::create(['vendor_id' => $this->vendor->id, 'status' => 'receiving', 'created_by' => $this->user->id]);
        $line   = PalletLine::create([
            'pallet_id'             => $pallet->id,
            'line_number'           => 1,
            'description'           => 'TCG Packs',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 3,
            'quantity_per_case'     => 10,
            'unit_cost'             => 4.50,
        ]);

        $count = $this->service->receiveAllCasesForLine($line);

        $this->assertEquals(3, $count);

        $stock = InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('inventory_location_id', $this->location->id)
            ->value('quantity');

        $this->assertEquals(30, (float) $stock); // 3 cases × 10 units
    }

    public function test_map_line_updates_item_and_location(): void
    {
        $pallet = Pallet::create(['vendor_id' => $this->vendor->id, 'status' => 'pending', 'created_by' => $this->user->id]);
        $line   = PalletLine::create([
            'pallet_id'   => $pallet->id,
            'line_number' => 1,
            'description' => 'Mystery item',
            'case_count'  => 1,
            'quantity_per_case' => 1,
            'unit_cost'   => 0,
        ]);

        $this->service->mapLine($line, $this->item, $this->location);

        $line->refresh();
        $this->assertEquals($this->item->id, $line->inventory_item_id);
        $this->assertEquals($this->location->id, $line->inventory_location_id);
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\ReceivingSession;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReceivingSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * completeSession() receives cases through ReceivingService::receiveAllCasesForLine(),
 * which already opens a lot for a costed receipt — this only attaches the
 * session's own paperwork (invoice/PO) to that lot. It must never also create
 * a second lot for the same receipt, which would double-count the quantity
 * feeding average_cost.
 */
class ReceivingSessionLotTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_session_opens_exactly_one_lot_per_line(): void
    {
        $this->actingAs(User::factory()->create());

        $vendor   = Vendor::create(['name' => 'V', 'status' => 'active']);
        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $item     = InventoryItem::create(['name' => 'Box', 'sku' => 'B1', 'average_cost' => 0, 'is_active' => true]);

        $session = ReceivingSession::create([
            'vendor_id'       => $vendor->id,
            'received_by'     => auth()->id(),
            'invoice_number'  => 'INV-1',
            'purchase_order'  => 'PO-1',
            'status'          => ReceivingSession::STATUS_REVIEWING,
        ]);

        $pallet = Pallet::create([
            'vendor_id' => $vendor->id,
            'reference' => 'PO-1',
            'status'    => 'receiving',
            'receiving_session_id' => $session->id,
        ]);

        $line = PalletLine::create([
            'pallet_id'             => $pallet->id,
            'receiving_session_id' => $session->id,
            'line_number'           => 1,
            'description'           => 'Box',
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $location->id,
            'case_count'            => 2,
            'quantity_per_case'     => 5,
            'unit_cost'             => 20,
        ]);

        $result = app(ReceivingSessionService::class)->completeSession($session->fresh());

        $this->assertSame(1, $result['lots_created']);
        $this->assertSame(1, InventoryLot::where('pallet_line_id', $line->id)->count());

        $lot = InventoryLot::where('pallet_line_id', $line->id)->first();
        $this->assertEquals(10, (float) $lot->remaining_quantity);
        $this->assertEquals(20, (float) $lot->unit_cost);
        $this->assertSame($session->id, $lot->receiving_session_id);
        $this->assertSame('INV-1', $lot->supplier_invoice);
        $this->assertSame('PO-1', $lot->purchase_order);

        $this->assertEquals(20.0000, (float) $item->fresh()->average_cost);
    }
}

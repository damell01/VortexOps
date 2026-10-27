<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\StockTransfer;
use App\Filament\Pages\InventoryReconciliation;
use App\Filament\Resources\InventoryItemResource\Pages\ManageStock;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $source;
    private InventoryLocation $destination;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
        $this->source = InventoryLocation::create(['name' => 'Audit Source', 'status' => 'active']);
        $this->destination = InventoryLocation::create(['name' => 'Audit Destination', 'status' => 'active']);
        $this->item = InventoryItem::create(['name' => 'Audit Box', 'sku' => 'AUDIT-BOX', 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $this->item->id, 'inventory_location_id' => $this->source->id, 'quantity' => 20]);
    }

    private function transfer()
    {
        return Livewire::test(StockTransfer::class)
            ->set('fromLocationId', $this->source->id)
            ->set('toLocationId', $this->destination->id);
    }

    public function test_bulk_controls_identify_the_product_and_persist_both_ends_once(): void
    {
        $page = $this->transfer()->assertSee('Audit Box');
        $this->assertSame($this->item->id, $page->instance()->sourceStock[0]['item_id']);
        $page->call('updateTransferQty', $this->item->id, '4')
            ->set('showConfirm', true)->assertSee('Confirm Transfer')
            ->call('executeTransfer')->assertSet('selectedTransfers', [])->assertSet('showConfirm', false);
        $this->assertDatabaseHas('inventory_stock', ['inventory_item_id' => $this->item->id, 'inventory_location_id' => $this->source->id, 'quantity' => 16]);
        $this->assertDatabaseHas('inventory_stock', ['inventory_item_id' => $this->item->id, 'inventory_location_id' => $this->destination->id, 'quantity' => 4]);
        $this->assertDatabaseHas('inventory_movements', ['inventory_item_id' => $this->item->id, 'from_location_id' => $this->source->id, 'to_location_id' => $this->destination->id, 'quantity' => 4]);
        $page->call('executeTransfer');
        $this->assertSame(1, InventoryMovement::where('movement_type', 'transfer')->count());
    }

    public function test_a_failed_batch_rolls_back_every_item_before_retry(): void
    {
        $second = InventoryItem::create(['name' => 'Unavailable', 'is_active' => true]);
        $page = $this->transfer()->call('updateTransferQty', $this->item->id, '4')
            ->call('updateTransferQty', $second->id, '1')->call('executeTransfer');
        $this->assertDatabaseHas('inventory_stock', ['inventory_item_id' => $this->item->id, 'quantity' => 20]);
        $this->assertSame(0, InventoryMovement::where('movement_type', 'transfer')->count());
        $page->call('executeTransfer');
        $this->assertDatabaseHas('inventory_stock', ['inventory_item_id' => $this->item->id, 'quantity' => 20]);
    }

    public function test_switching_source_clears_quantities_and_confirmation(): void
    {
        $this->transfer()->call('updateTransferQty', $this->item->id, '4')
            ->set('showConfirm', true)->set('fromLocationId', $this->destination->id)
            ->assertSet('selectedTransfers', [])->assertSet('showConfirm', false)->assertSet('toLocationId', null);
    }

    public function test_blank_adjustment_does_not_reset_stock_to_zero(): void
    {
        Livewire::test(ManageStock::class, ['record' => $this->item->id])
            ->set('newQuantity', '')->call('submit')->assertHasErrors(['newQuantity']);
        $this->assertDatabaseHas('inventory_stock', ['inventory_item_id' => $this->item->id, 'quantity' => 20]);
    }

    public function test_invalid_transfer_quantities_are_rejected(): void
    {
        foreach (['', 'not a number', '-2', '0'] as $quantity) {
            Livewire::test(ManageStock::class, ['record' => $this->item->id])
                ->call('setOperation', ManageStock::TRANSFER)->set('toLocationId', $this->destination->id)
                ->set('moveQuantity', $quantity)->call('submit')->assertHasErrors(['moveQuantity']);
        }
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_service_rejects_invalid_and_same_location_transfers_without_writes(): void
    {
        foreach ([-1, 0, INF, NAN] as $quantity) {
            try {
                app(InventoryService::class)->transferStock($this->item, $this->source, $this->destination, $quantity);
                $this->fail('Invalid transfer accepted');
            } catch (\RuntimeException) {}
        }
        try {
            app(InventoryService::class)->transferStock($this->item, $this->source, $this->source, 1);
            $this->fail('Same location accepted');
        } catch (\RuntimeException) {}
        $this->assertDatabaseHas('inventory_stock', ['inventory_item_id' => $this->item->id, 'quantity' => 20]);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_inactive_destination_is_rejected(): void
    {
        $this->destination->update(['status' => 'inactive']);
        $this->transfer()->call('updateTransferQty', $this->item->id, '4')->call('executeTransfer');
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_reconciliation_rejects_non_numeric_counts(): void
    {
        Livewire::test(InventoryReconciliation::class)->set('selectedLocationId', $this->source->id)
            ->set('selectedItemId', $this->item->id)->set('physicalCount', 'bad')
            ->call('reconcileItem')->assertHasErrors(['physicalCount']);
        $this->assertDatabaseHas('inventory_stock', ['inventory_item_id' => $this->item->id, 'quantity' => 20]);
    }

    public function test_scanner_delta_preserves_the_latest_count_and_cannot_overdraw(): void
    {
        $service = app(InventoryService::class);
        $service->adjustStockBy($this->item, $this->source, 3);
        $service->adjustStockBy($this->item, $this->source, -2);
        $this->assertDatabaseHas('inventory_stock', ['inventory_item_id' => $this->item->id, 'quantity' => 21]);
        $this->expectException(\RuntimeException::class);
        $service->adjustStockBy($this->item, $this->source, -100);
    }
    public function test_combined_receipts_keep_the_correct_before_after_and_signed_quantity(): void
    {
        $service = app(InventoryService::class);
        $service->addStock($this->item, $this->source, 2, 'opening', 'Received via pallet #AUDIT');
        $service->addStock($this->item, $this->source, 3, 'opening', 'Received via pallet #AUDIT');
        $movement = InventoryMovement::first();
        $this->assertSame(1, InventoryMovement::count());
        $this->assertEquals(20, $movement->quantity_before);
        $this->assertEquals(25, $movement->quantity_after);
        $this->assertEquals(5, $movement->signedChange());
    }

    public function test_receipts_separated_by_another_stock_change_keep_separate_levels(): void
    {
        $service = app(InventoryService::class);
        $service->addStock($this->item, $this->source, 2, 'opening', 'Received via pallet #AUDIT');
        $service->transferStock($this->item, $this->source, $this->destination, 1);
        $service->addStock($this->item, $this->source, 3, 'opening', 'Received via pallet #AUDIT');
        $this->assertSame(3, InventoryMovement::count());
        $movement = InventoryMovement::latest('id')->first();
        $this->assertEquals(21, $movement->quantity_before);
        $this->assertEquals(24, $movement->quantity_after);
        $this->assertEquals(3, $movement->signedChange());
    }

}

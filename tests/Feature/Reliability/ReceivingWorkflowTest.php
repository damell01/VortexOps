<?php

namespace Tests\Feature\Reliability;

use App\Filament\Resources\PalletResource\Pages\ReceivePallet;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceivingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function delivery(): array
    {
        $this->enableAdminModules();
        $user = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($user);
        $vendor = Vendor::create(['name' => 'Receiving test', 'status' => 'active']);
        $location = InventoryLocation::create(['name' => 'Dock', 'type' => 'main_storage', 'status' => 'active']);
        $item = InventoryItem::create(['name' => 'Test boxes', 'sku' => 'RCV-TEST', 'barcode' => 'RCV-CODE', 'unit_cost' => 5, 'is_active' => true]);
        $pallet = Pallet::create(['vendor_id' => $vendor->id, 'reference' => 'RECEIVING-TEST', 'status' => 'receiving']);
        $line = PalletLine::create(['pallet_id' => $pallet->id, 'description' => 'Test boxes', 'line_number' => 1,
            'inventory_item_id' => $item->id, 'inventory_location_id' => $location->id,
            'case_count' => 4, 'quantity_per_case' => 2, 'unit_cost' => 5]);
        app(ReceivingService::class)->generateExpectedCases($line);
        return [$pallet, $line, $item, $location];
    }

    public function test_a_stale_case_cannot_credit_stock_twice(): void
    {
        [$pallet, $line, $item] = $this->delivery();
        $case = $line->cases()->first();
        $service = app(ReceivingService::class);
        $service->receiveCase($case);
        try {
            $service->receiveCase($case);
            $this->fail('A repeated stale case must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already been received', $e->getMessage());
        }
        $this->assertEquals(2, InventoryStock::where('inventory_item_id', $item->id)->sum('quantity'));
    }

    public function test_partial_delivery_summary_does_not_receive_outstanding_stock(): void
    {
        [$pallet, $line, $item] = $this->delivery();
        $service = app(ReceivingService::class);
        $service->receiveCasesForLine($line, 1);
        $review = $service->reviewPallet($pallet->fresh());
        $this->assertEquals(2, $review['totals']['confirmed_units']);
        $this->assertEquals(6, $review['totals']['short_units']);
        $this->assertEquals(2, InventoryStock::where('inventory_item_id', $item->id)->sum('quantity'));
        $this->assertStringContainsString('Outstanding units', view('filament.components.receiving-summary', ['review' => $review])->render());
    }

    public function test_receiver_and_unfinished_quantity_restore_after_returning(): void
    {
        [$pallet, $line, $item, $location] = $this->delivery();
        Livewire::test(ReceivePallet::class, ['record' => $pallet])
            ->set('receivedByName', 'Receiving teammate')
            ->set('partialReceiveQuantity.' . $line->id, 2)
            ->set('receivingLocationId', $location->id);
        Livewire::test(ReceivePallet::class, ['record' => $pallet])
            ->assertSet('receivedByName', 'Receiving teammate')
            ->assertSet('partialReceiveQuantity.' . $line->id, 2)
            ->assertSet('receivingLocationId', $location->id)
            ->assertSee('Review & complete');
        $this->assertEquals(0, InventoryStock::where('inventory_item_id', $item->id)->sum('quantity'));
    }

    public function test_product_scan_only_matches_then_receiving_requires_quantity_confirmation(): void
    {
        [$pallet, $line, $item] = $this->delivery();
        $component = Livewire::test(ReceivePallet::class, ['record' => $pallet])
            ->set('barcodeInput', 'RCV-CODE')->call('submitBarcode')->assertSet('lastScanSuccess', true);
        $this->assertEquals(0, InventoryStock::where('inventory_item_id', $item->id)->sum('quantity'));
        $component->assertSet('targetLineId', $line->id)->set('partialReceiveQuantity.' . $line->id, 1)->call('receivePartialLine', $line->id, $component->get('receiptRequestId'));
        $requestId = $component->get('receiptRequestId');
        $received = $line->cases()->where('status', 'received')->first();
        $received->update(['barcode' => 'UNIQUE-CASE-TEST']);
        $component->set('barcodeInput', $received->barcode)->call('submitBarcode')->assertSet('lastScanSuccess', false);
        $this->assertEquals(2, InventoryStock::where('inventory_item_id', $item->id)->sum('quantity'));
    }
    public function test_history_opens_from_inventory_without_changing_stock(): void
    {
        [$pallet, $line, $item] = $this->delivery();
        app(ReceivingService::class)->receiveCasesForLine($line, 1);
        Livewire::test(\App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems::class)
            ->call('mountAction', 'itemHistory', ['item' => $item->id])
            ->assertSee('Received via pallet')
            ->assertSee('Test boxes');
        $this->assertEquals(2, InventoryStock::where('inventory_item_id', $item->id)->sum('quantity'));
    }

    public function test_replaying_a_partial_receipt_does_not_receive_the_next_cases(): void
    {
        [$pallet, $line, $item] = $this->delivery();
        $component = Livewire::test(ReceivePallet::class, ['record' => $pallet]);
        $requestId = $component->get('receiptRequestId');
        $component->set('partialReceiveQuantity.' . $line->id, 1)
            ->call('receivePartialLine', $line->id, $requestId)
            ->set('partialReceiveQuantity.' . $line->id, 1)
            ->call('receivePartialLine', $line->id, $requestId);
        $this->assertEquals(2, InventoryStock::where('inventory_item_id', $item->id)->sum('quantity'));
    }

}

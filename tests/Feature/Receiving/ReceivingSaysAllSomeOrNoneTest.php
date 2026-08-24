<?php

namespace Tests\Feature\Receiving;

use App\Filament\Pages\InventoryScanner;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\MissingItemReport;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A delivery has three answers, and all three belong on one screen.
 *
 * "Did it turn up" is answered all, some, or none — and only two of those were
 * reachable, from different pages. Receiving all of a line lived on the pallet
 * page; scanning case by case lived on the scanning station; closing short
 * lived in a modal on a third. Which answer you could give depended on which
 * page you had open, so a box that came six-of-ten meant scanning six barcodes
 * you were already holding.
 *
 * The scanner is where this happens now, because it is the screen someone
 * stands at with a box. Scanning fills the middle answer in rather than being
 * a separate mode.
 *
 * Receive mode itself was written, documented on $mode, wired into
 * submitScan() — and left out of MODES, so switchMode() bounced it back to
 * lookup and none of it could be reached.
 */
class ReceivingSaysAllSomeOrNoneTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Pallet $pallet;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->admin);

        $this->location = InventoryLocation::create(['name' => 'Main Warehouse', 'is_active' => true]);

        $this->pallet = Pallet::create([
            'vendor_id'  => Vendor::create(['name' => 'Topps'])->id,
            'reference'  => 'PO-4471',
            'status'     => 'receiving',
            'created_by' => $this->admin->id,
        ]);
    }

    private function line(string $name, int $cases, float $perCase = 12): PalletLine
    {
        $item = InventoryItem::create([
            'name' => $name, 'sku' => strtoupper(substr($name, 0, 3)) . '-1',
            'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
        ]);

        return PalletLine::create([
            'pallet_id'             => $this->pallet->id,
            'line_number'           => ((int) $this->pallet->lines()->max('line_number')) + 1,
            'description'           => $name,
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => $cases,
            'quantity_per_case'     => $perCase,
            'unit_cost'             => 10,
            'line_status'           => 'pending',
        ]);
    }

    private function scanner()
    {
        return Livewire::test(InventoryScanner::class)
            ->call('switchMode', 'receive')
            ->set('rcvPalletId', $this->pallet->id);
    }

    public function test_receive_mode_can_actually_be_reached(): void
    {
        $this->assertContains('receive', InventoryScanner::MODES);

        Livewire::test(InventoryScanner::class)
            ->call('switchMode', 'receive')
            ->assertSet('mode', 'receive');
    }

    public function test_all_arrived_takes_the_whole_line(): void
    {
        $line = $this->line('Chrome Hobby', 4);

        $this->scanner()->call('receiveAllOfLine', $line->id)->assertOk();

        $this->assertSame(4, $line->fresh()->receivedCases());
        $this->assertSame(48.0, (float) $line->inventoryItem->stock()->sum('quantity'));
    }

    public function test_some_arrived_takes_only_that_many(): void
    {
        // The answer that had nowhere to go: six of ten, without scanning six
        // barcodes you are already holding.
        $line = $this->line('Chrome Hobby', 10);

        $this->scanner()->call('receiveSomeOfLine', $line->id, 6)->assertOk();

        $this->assertSame(6, $line->fresh()->receivedCases());
        $this->assertSame(72.0, (float) $line->inventoryItem->stock()->sum('quantity'));
    }

    public function test_asking_for_more_than_is_outstanding_takes_what_is_left(): void
    {
        // A manifest that undercounted is not a reason to refuse the delivery.
        $line = $this->line('Chrome Hobby', 3);

        $this->scanner()->call('receiveSomeOfLine', $line->id, 99)->assertOk();

        $this->assertSame(3, $line->fresh()->receivedCases());
    }

    public function test_not_here_reports_the_rest_missing(): void
    {
        $line = $this->line('Chrome Hobby', 10);

        $this->scanner()
            ->call('receiveSomeOfLine', $line->id, 4)
            ->call('markLineShort', $line->id)
            ->assertOk();

        $report = MissingItemReport::firstWhere('pallet_line_id', $line->id);

        $this->assertNotNull($report, 'nothing was filed for the cases that never came');
        $this->assertSame(6, (int) $report->expected_quantity);
    }

    public function test_a_line_from_another_pallet_cannot_be_touched(): void
    {
        // The line id comes from the browser. Without the pallet in the where
        // clause it would address any line in the database.
        $otherPallet = Pallet::create(['reference' => 'PO-9999', 'status' => 'receiving', 'created_by' => $this->admin->id]);
        $mine = $this->line('Chrome Hobby', 4);
        $this->pallet = $otherPallet;
        $theirs = $this->line('Someone Else Hobby', 4);

        Livewire::test(InventoryScanner::class)
            ->call('switchMode', 'receive')
            ->set('rcvPalletId', $mine->pallet_id)
            ->call('receiveAllOfLine', $theirs->id)
            ->assertSet('rcvError', 'That line is not on this pallet.');

        $this->assertSame(0, $theirs->fresh()->receivedCases());
    }

    public function test_a_delivery_can_start_with_no_manifest_at_all(): void
    {
        // The gate this removes: "Start receiving" used to appear only once
        // lines existed, so an unannounced box had to be keyed in as
        // expectations first — expectations nothing downstream reads.
        $page = Livewire::test(InventoryScanner::class)->call('startBlankPallet')->assertOk();

        $palletId = $page->instance()->rcvPalletId;

        $this->assertNotNull($palletId);
        $this->assertSame('receive', $page->instance()->mode);
        $this->assertSame(0, Pallet::find($palletId)->lines()->count());
    }

    public function test_something_not_on_the_manifest_is_added_and_received_in_one_step(): void
    {
        $item = InventoryItem::create([
            'name' => 'Surprise Box', 'sku' => 'SUR-1',
            'unit_cost' => 25, 'average_cost' => 25, 'is_active' => true,
        ]);

        $this->scanner()
            ->call('receiveUnlistedItem', $item->id, $this->location->id, 2, 6, 25)
            ->assertOk();

        $line = PalletLine::firstWhere('inventory_item_id', $item->id);

        $this->assertNotNull($line, 'no line was created for the unlisted item');
        $this->assertSame($this->pallet->id, $line->pallet_id);
        $this->assertSame(2, $line->fresh()->receivedCases());
        $this->assertSame(12.0, (float) $item->stock()->sum('quantity'));
    }

    public function test_an_unlisted_item_with_no_price_keeps_the_item_average(): void
    {
        // Defaulting the cost to zero would drag the weighted average down on
        // every walk-in, which is a quiet way to corrupt margin reporting.
        $item = InventoryItem::create([
            'name' => 'Unpriced Box', 'sku' => 'UNP-1',
            'unit_cost' => 30, 'average_cost' => 30, 'is_active' => true,
        ]);

        $this->scanner()
            ->call('receiveUnlistedItem', $item->id, $this->location->id, 1, 1, null)
            ->assertOk();

        $this->assertEqualsWithDelta(30.0, (float) $item->fresh()->average_cost, 0.01);
    }

    public function test_receiving_without_a_pallet_says_so_instead_of_failing(): void
    {
        Livewire::test(InventoryScanner::class)
            ->call('switchMode', 'receive')
            ->call('receiveAllOfLine', 1)
            ->assertSet('rcvError', 'Select a pallet first.');
    }

    public function test_an_unlisted_item_can_be_priced_as_it_is_added(): void
    {
        // receiveUnlistedItem() shipped with a $unitCost parameter and nothing
        // on screen to call it. The two manifest editors that do take a cost
        // are the screen you are not standing at with the box.
        $item = InventoryItem::create([
            'name' => 'Walk-in Case', 'sku' => 'WLK-1',
            'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
        ]);

        Livewire::test(InventoryScanner::class)
            ->call('switchMode', 'receive')
            ->set('rcvPalletId', $this->pallet->id)
            ->call('selectManualItem', $item->id)
            ->set('manualCaseCount', '2')
            ->set('manualQtyPerCase', '5')
            ->set('manualUnitCost', '42.50')
            ->set('manualLocationId', $this->location->id)
            ->call('addUnlistedToDelivery')
            ->assertOk()
            ->assertSet('rcvError', null);

        $line = PalletLine::firstWhere('inventory_item_id', $item->id);

        $this->assertNotNull($line);
        $this->assertEqualsWithDelta(42.50, (float) $line->unit_cost, 0.01);
        $this->assertSame(2, $line->fresh()->receivedCases());
        $this->assertSame(10.0, (float) $item->stock()->sum('quantity'));
    }

    public function test_the_form_clears_after_a_successful_add(): void
    {
        $item = InventoryItem::create([
            'name' => 'Walk-in Case', 'sku' => 'WLK-2',
            'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
        ]);

        Livewire::test(InventoryScanner::class)
            ->call('switchMode', 'receive')
            ->set('rcvPalletId', $this->pallet->id)
            ->call('selectManualItem', $item->id)
            ->set('manualUnitCost', '9.99')
            ->set('manualLocationId', $this->location->id)
            ->call('addUnlistedToDelivery')
            ->assertSet('manualItemId', null)
            ->assertSet('manualUnitCost', '')
            ->assertSet('manualCaseCount', '1');
    }

    public function test_adding_without_choosing_an_item_says_so(): void
    {
        Livewire::test(InventoryScanner::class)
            ->call('switchMode', 'receive')
            ->set('rcvPalletId', $this->pallet->id)
            ->call('addUnlistedToDelivery')
            ->assertSet('rcvError', 'Pick an item first.');
    }
}

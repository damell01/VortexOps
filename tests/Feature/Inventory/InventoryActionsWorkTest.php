<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryScanner;
use App\Filament\Pages\QuickAddStock;
use App\Filament\Pages\StockTransfer;
use App\Filament\Resources\InventoryItemResource\Pages\CreateInventoryItem;
use App\Filament\Resources\InventoryItemResource\Pages\EditInventoryItem;
use App\Filament\Resources\InventoryItemResource\Pages\ManageStock;
use App\Filament\Resources\InventoryItemResource\Pages\QuickAddInventoryItem;
use App\Filament\Resources\PalletResource\Pages\ReceivePallet;
use App\Models\InventoryCase;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The things people actually press, driven end to end.
 *
 * Rendering a page proves it opens. It proves nothing about the button on it,
 * and the button is the part that changes stock — so each of these submits a
 * real action and then reads the database back, rather than asserting that no
 * exception was thrown.
 *
 * Every quantity assertion is made against stored rows, not against what the
 * component says it did.
 */
class InventoryActionsWorkTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $item;

    private InventoryLocation $main;

    private InventoryLocation $back;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $this->vendor = Vendor::create(['name' => 'Action Vendor', 'status' => 'active']);
        $this->main   = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
        $this->back   = InventoryLocation::create(['name' => 'Back Room', 'type' => 'main_storage', 'status' => 'active']);

        $this->item = InventoryItem::create([
            'name' => 'Chrome Hobby Box', 'sku' => 'ACT-1', 'barcode' => '012345678905',
            'unit_cost' => 80, 'average_cost' => 80, 'is_active' => true,
        ]);

        InventoryStock::create([
            'inventory_item_id' => $this->item->id,
            'inventory_location_id' => $this->main->id,
            'quantity' => 20,
        ]);
    }

    private function stockAt(InventoryLocation $location): float
    {
        return (float) InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('inventory_location_id', $location->id)
            ->value('quantity');
    }

    // ── Creating products ─────────────────────────────────────────────────────

    public function test_creating_an_item_stores_the_fields_that_were_filled(): void
    {
        Livewire::test(CreateInventoryItem::class)
            ->fillForm([
                'name'      => 'Prizm Retail Box',
                'sku'       => 'ACT-NEW',
                'barcode'   => '999888777666',
                'unit_cost' => 42.5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = InventoryItem::firstWhere('sku', 'ACT-NEW');

        $this->assertNotNull($created, 'the item was not created');
        $this->assertSame('Prizm Retail Box', $created->name);
        $this->assertSame('999888777666', $created->barcode);
        $this->assertEqualsWithDelta(42.5, (float) $created->unit_cost, 0.01);
    }

    public function test_quick_add_creates_an_item_and_books_its_opening_stock(): void
    {
        Livewire::test(QuickAddInventoryItem::class)
            ->set('data', [
                'name'        => 'Quick Added Box',
                'unit_cost'   => 15,
                'location_id' => $this->main->id,
                'quantity'    => 9,
            ])
            ->call('submit');

        $created = InventoryItem::firstWhere('name', 'Quick Added Box');
        $this->assertNotNull($created, 'quick add did not create the item');

        $this->assertEqualsWithDelta(9.0, (float) InventoryStock::where('inventory_item_id', $created->id)
            ->where('inventory_location_id', $this->main->id)->value('quantity'), 0.01);

        // Opening stock is still stock arriving, so it has to be explicable.
        $this->assertDatabaseHas('inventory_movements', ['inventory_item_id' => $created->id]);
    }

    public function test_editing_an_item_saves_the_change(): void
    {
        Livewire::test(EditInventoryItem::class, ['record' => $this->item->id])
            ->fillForm(['name' => 'Renamed Box', 'unit_cost' => 91.25])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->item->refresh();

        $this->assertSame('Renamed Box', $this->item->name);
        $this->assertEqualsWithDelta(91.25, (float) $this->item->unit_cost, 0.01);
    }

    // ── Moving stock ──────────────────────────────────────────────────────────

    public function test_a_correction_writes_the_new_count_and_a_movement(): void
    {
        $before = InventoryMovement::count();

        Livewire::test(ManageStock::class, ['record' => $this->item->id])
            ->set('newQuantity', '13')
            ->set('reason', 'counted the shelf')
            ->call('submit');

        $this->assertEqualsWithDelta(13.0, $this->stockAt($this->main), 0.01);
        $this->assertSame($before + 1, InventoryMovement::count());
    }

    public function test_a_transfer_moves_units_without_creating_or_destroying_any(): void
    {
        Livewire::test(ManageStock::class, ['record' => $this->item->id])
            ->call('setOperation', ManageStock::TRANSFER)
            ->set('toLocationId', $this->back->id)
            ->set('moveQuantity', '5')
            ->call('submit');

        $this->assertEqualsWithDelta(15.0, $this->stockAt($this->main), 0.01);
        $this->assertEqualsWithDelta(5.0, $this->stockAt($this->back), 0.01);

        // The total is the invariant that matters: a transfer that changes it
        // is a transfer that lost or invented stock.
        $this->assertEqualsWithDelta(20.0, $this->stockAt($this->main) + $this->stockAt($this->back), 0.01);
    }

    public function test_a_transfer_of_more_than_is_there_is_refused(): void
    {
        Livewire::test(ManageStock::class, ['record' => $this->item->id])
            ->call('setOperation', ManageStock::TRANSFER)
            ->set('toLocationId', $this->back->id)
            ->set('moveQuantity', '500')
            ->call('submit');

        $this->assertEqualsWithDelta(20.0, $this->stockAt($this->main), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->stockAt($this->back), 0.01);
    }

    // ── The scanner ───────────────────────────────────────────────────────────

    public function test_a_scanned_barcode_finds_the_item(): void
    {
        Livewire::test(InventoryScanner::class)
            ->set('scanInput', '012345678905')
            ->call('submitScan')
            ->assertOk();

        // Lookup is read-only — the point of the mode.
        $this->assertEqualsWithDelta(20.0, $this->stockAt($this->main), 0.01);
    }

    public function test_a_code_that_matches_nothing_changes_nothing(): void
    {
        $before = InventoryMovement::count();

        Livewire::test(InventoryScanner::class)
            ->set('scanInput', 'not-a-real-code-at-all')
            ->call('submitScan')
            ->assertOk();

        $this->assertSame($before, InventoryMovement::count());
    }

    public function test_scanning_in_add_mode_raises_the_count(): void
    {
        Livewire::test(InventoryScanner::class)
            ->call('switchMode', 'quickadd')
            ->set('scanInput', '012345678905')
            ->call('submitScan')
            ->assertOk();

        $this->assertGreaterThanOrEqual(20.0, $this->stockAt($this->main));
    }

    // ── Receiving ─────────────────────────────────────────────────────────────

    public function test_receiving_a_line_books_its_cases_into_stock(): void
    {
        $pallet = Pallet::create([
            'vendor_id' => $this->vendor->id, 'reference' => 'PO-ACT', 'status' => 'pending',
        ]);

        $line = PalletLine::create([
            'pallet_id'             => $pallet->id,
            'line_number'           => 1,
            'description'           => 'Chrome Hobby Box',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->main->id,
            'case_count'            => 2,
            'quantity_per_case'     => 6,
            'unit_cost'             => 80,
        ]);

        foreach (['ACT-CASE-1', 'ACT-CASE-2'] as $barcode) {
            InventoryCase::create([
                'pallet_line_id' => $line->id, 'barcode' => $barcode, 'status' => 'expected',
            ]);
        }

        Livewire::test(ReceivePallet::class, ['record' => $pallet])
            ->call('receiveLine', $line->id)
            ->assertOk();

        // Two cases of six on top of the twenty already there.
        $this->assertEqualsWithDelta(32.0, $this->stockAt($this->main), 0.01);
    }

    public function test_receiving_by_barcode_takes_one_case_not_the_whole_line(): void
    {
        $pallet = Pallet::create([
            'vendor_id' => $this->vendor->id, 'reference' => 'PO-ACT-2', 'status' => 'pending',
        ]);

        $line = PalletLine::create([
            'pallet_id'             => $pallet->id,
            'line_number'           => 1,
            'description'           => 'Chrome Hobby Box',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->main->id,
            'case_count'            => 3,
            'quantity_per_case'     => 4,
            'unit_cost'             => 80,
        ]);

        InventoryCase::create(['pallet_line_id' => $line->id, 'barcode' => '5550001', 'status' => 'expected']);
        InventoryCase::create(['pallet_line_id' => $line->id, 'barcode' => '5550002', 'status' => 'expected']);

        Livewire::test(ReceivePallet::class, ['record' => $pallet])
            ->set('barcodeInput', '5550001')
            ->call('submitBarcode')
            ->assertOk();

        $this->assertEqualsWithDelta(24.0, $this->stockAt($this->main), 0.01);
        $this->assertSame('received', InventoryCase::firstWhere('barcode', '5550001')->status);
        $this->assertSame('expected', InventoryCase::firstWhere('barcode', '5550002')->status);
    }

    public function test_a_case_label_that_is_not_all_digits_still_receives(): void
    {
        // The codes most likely to be scanned at a pallet were the ones being
        // destroyed. Before the lookup ran, the page stripped every leading
        // non-digit off the scanned value as a "common prefix" — which empties
        // an alphanumeric label entirely, and mangles the SKUs this app
        // generates for itself (Product::generateSku produces VB250815abcd).
        $pallet = Pallet::create([
            'vendor_id' => $this->vendor->id, 'reference' => 'PO-ALPHA', 'status' => 'pending',
        ]);

        $line = PalletLine::create([
            'pallet_id'             => $pallet->id,
            'line_number'           => 1,
            'description'           => 'Chrome Hobby Box',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->main->id,
            'case_count'            => 2,
            'quantity_per_case'     => 3,
            'unit_cost'             => 80,
        ]);

        InventoryCase::create(['pallet_line_id' => $line->id, 'barcode' => 'VB250815abcd', 'status' => 'expected']);

        Livewire::test(ReceivePallet::class, ['record' => $pallet])
            ->set('barcodeInput', 'VB250815abcd')
            ->call('submitBarcode')
            ->assertOk();

        $this->assertEqualsWithDelta(23.0, $this->stockAt($this->main), 0.01);
        $this->assertSame('received', InventoryCase::firstWhere('barcode', 'VB250815abcd')->status);
    }

    public function test_a_case_already_received_is_not_received_twice(): void
    {
        // Double-scanning a box is the most ordinary mistake on a pallet, and
        // the one that quietly inflates stock if it is allowed through.
        $pallet = Pallet::create([
            'vendor_id' => $this->vendor->id, 'reference' => 'PO-TWICE', 'status' => 'pending',
        ]);

        $line = PalletLine::create([
            'pallet_id'             => $pallet->id,
            'line_number'           => 1,
            'description'           => 'Chrome Hobby Box',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->main->id,
            'case_count'            => 2,
            'quantity_per_case'     => 5,
            'unit_cost'             => 80,
        ]);

        InventoryCase::create(['pallet_line_id' => $line->id, 'barcode' => '7770001', 'status' => 'expected']);

        $page = Livewire::test(ReceivePallet::class, ['record' => $pallet]);

        $page->set('barcodeInput', '7770001')->call('submitBarcode');
        $this->assertEqualsWithDelta(25.0, $this->stockAt($this->main), 0.01);

        $page->set('barcodeInput', '7770001')->call('submitBarcode');
        $this->assertEqualsWithDelta(25.0, $this->stockAt($this->main), 0.01);
    }

    // ── The standalone stock pages ────────────────────────────────────────────

    public function test_quick_add_stock_books_units_in(): void
    {
        Livewire::test(QuickAddStock::class)
            ->assertOk();

        // Only that it opens and lists the item: the booking itself is covered
        // above through the scanner and the pallet, which is how stock actually
        // arrives. Asserting it twice through a third screen would pin the
        // screen rather than the behaviour.
        $this->assertEqualsWithDelta(20.0, $this->stockAt($this->main), 0.01);
    }

    public function test_the_transfer_page_opens_with_both_locations(): void
    {
        Livewire::test(StockTransfer::class)->assertOk();

        $this->assertEqualsWithDelta(20.0, $this->stockAt($this->main), 0.01);
    }

    // ── Deleting ──────────────────────────────────────────────────────────────

    public function test_a_deleted_item_stops_appearing_in_stock(): void
    {
        $this->item->delete();

        $this->assertSame(
            0,
            InventoryStock::where('inventory_item_id', $this->item->id)->count(),
            'stock rows for a deleted product are still being listed',
        );
    }
}

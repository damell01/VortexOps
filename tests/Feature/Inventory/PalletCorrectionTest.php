<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PalletCorrectionService;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correcting a pallet after the fact, without lying about what happened.
 *
 * A pallet is typed from a packing slip and scanned in a warehouse, so some of
 * it is wrong: a cost mistyped, a miscount, a line put in the wrong place. The
 * tempting fix is to set the row to what it should have been — and for stock
 * that is exactly wrong, because the total is the sum of what moved. A receipt
 * rewritten in place leaves the pallet claiming one thing and the movement
 * history another, with nothing saying which is the correction.
 *
 * So these split on the distinction the service is built around: cost and name
 * are facts about a purchase and get rewritten; quantity and location describe
 * where stock is and go through the ledger.
 */
class PalletCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Pallet $pallet;
    private InventoryLocation $main;
    private InventoryLocation $other;
    private PalletCorrectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $this->main  = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $this->other = InventoryLocation::create(['name' => 'Back Room', 'type' => 'main_storage', 'status' => 'active']);

        $this->pallet = Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'V', 'status' => 'active'])->id,
            'name'      => 'Topps Chrome — August',
            'status'    => 'receiving',
        ]);

        $this->service = app(PalletCorrectionService::class);
    }

    /** A line that has actually been received, so there is stock to correct. */
    private function receivedLine(float $unitCost = 100, int $cases = 2, float $perCase = 5): PalletLine
    {
        $item = InventoryItem::create([
            'name' => 'Chrome Box', 'sku' => 'CHR-1', 'barcode' => '111',
            'unit_cost' => $unitCost, 'average_cost' => 0, 'is_active' => true,
        ]);

        $line = $this->pallet->lines()->create([
            'line_number'           => 1,
            'description'           => 'Chrome Box',
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->main->id,
            'case_count'            => $cases,
            'quantity_per_case'     => $perCase,
            'unit_cost'             => $unitCost,
        ]);

        $receiving = app(ReceivingService::class);
        $receiving->generateExpectedCases($line->refresh());

        foreach ($line->cases as $case) {
            $receiving->receiveCase($case);
        }

        return $line->fresh();
    }

    // ── Facts about a purchase: rewritten ─────────────────────────────────

    public function test_renaming_changes_the_product_not_the_manifest(): void
    {
        // The line keeps what the slip said. That is the record of the
        // paperwork, and losing it loses why the product was called what it was.
        $line = $this->receivedLine();

        $this->service->renameItem($line, 'Topps Chrome Hobby Box');

        $this->assertSame('Topps Chrome Hobby Box', $line->inventoryItem->fresh()->name);
        $this->assertSame('Chrome Box', $line->fresh()->description);
    }

    public function test_a_blank_name_is_refused(): void
    {
        $this->expectExceptionMessage('needs a name');

        $this->service->renameItem($this->receivedLine(), '   ');
    }

    public function test_correcting_the_cost_removes_the_wrong_figure_rather_than_averaging_it_in(): void
    {
        // 10 units received at 100 makes the average 100. Correcting to 80 has
        // to give 80 — not 90. Averaging the mistake with the correction leaves
        // it permanently baked in at a smaller weight, which is a bug that
        // hides itself by looking plausible.
        $line = $this->receivedLine(unitCost: 100, cases: 2, perCase: 5);

        $this->assertEqualsWithDelta(100.0, (float) $line->inventoryItem->fresh()->average_cost, 0.01);

        $this->service->correctUnitCost($line, 80);

        $this->assertEqualsWithDelta(80.0, (float) $line->inventoryItem->fresh()->average_cost, 0.01);
        $this->assertEqualsWithDelta(80.0, (float) $line->fresh()->unit_cost, 0.01);
    }

    public function test_a_negative_cost_is_refused(): void
    {
        $this->expectExceptionMessage('cannot be negative');

        $this->service->correctUnitCost($this->receivedLine(), -5);
    }

    public function test_correcting_a_cost_writes_no_stock_movement(): void
    {
        // Nothing moved. A cost correction that logged a movement would put an
        // event in the ledger for something that never happened.
        $line   = $this->receivedLine();
        $before = InventoryMovement::count();

        $this->service->correctUnitCost($line, 55);

        $this->assertSame($before, InventoryMovement::count());
    }

    // ── Where stock is: through the ledger ────────────────────────────────

    public function test_correcting_the_count_is_written_as_an_adjustment(): void
    {
        // Not by editing the receipt. The total is the sum of what moved, and
        // the correction has to be visible as a correction.
        $line = $this->receivedLine(cases: 2, perCase: 5);

        $this->service->correctQuantity($line, 8, 'two boxes were crushed');

        $movement = InventoryMovement::latest('id')->first();

        $this->assertSame('adjustment', $movement->movement_type);
        $this->assertEqualsWithDelta(-2.0, $movement->signedChange(), 0.01);
        $this->assertSame('two boxes were crushed', $movement->reason);
        $this->assertEqualsWithDelta(
            8.0,
            (float) InventoryStock::where('inventory_item_id', $line->inventory_item_id)->value('quantity'),
            0.01,
        );
    }

    public function test_the_reason_names_the_pallet_when_none_is_given(): void
    {
        // Six months later "why did this drop by two" is the only question
        // anyone asks, and the pallet is the answer.
        $line = $this->receivedLine();

        $this->service->correctQuantity($line, 9);

        $this->assertStringContainsString(
            'Topps Chrome — August',
            InventoryMovement::latest('id')->first()->reason,
        );
    }

    public function test_moving_a_line_is_written_as_a_transfer(): void
    {
        $line = $this->receivedLine(cases: 2, perCase: 5);

        $this->service->moveToLocation($line, $this->other, 'sorted to the back');

        $movement = InventoryMovement::latest('id')->first();

        $this->assertSame('transfer', $movement->movement_type);
        $this->assertSame($this->main->id, $movement->from_location_id);
        $this->assertSame($this->other->id, $movement->to_location_id);

        $this->assertEqualsWithDelta(0.0, $this->stockAt($line, $this->main), 0.01);
        $this->assertEqualsWithDelta(10.0, $this->stockAt($line, $this->other), 0.01);

        // The line follows, so the pallet keeps describing where its goods went.
        $this->assertSame($this->other->id, $line->fresh()->inventory_location_id);
    }

    public function test_moving_to_the_same_location_does_nothing(): void
    {
        $line   = $this->receivedLine();
        $before = InventoryMovement::count();

        $this->assertNull($this->service->moveToLocation($line, $this->main));
        $this->assertSame($before, InventoryMovement::count());
    }

    public function test_a_move_never_takes_more_than_is_there(): void
    {
        // Another pallet may have taken some of it. Transferring the line's
        // full quantity regardless is how a location ends up negative.
        $line = $this->receivedLine(cases: 2, perCase: 5);

        InventoryStock::where('inventory_item_id', $line->inventory_item_id)
            ->where('inventory_location_id', $this->main->id)
            ->update(['quantity' => 3]);

        $this->service->moveToLocation($line, $this->other);

        $this->assertEqualsWithDelta(0.0, $this->stockAt($line, $this->main), 0.01);
        $this->assertEqualsWithDelta(3.0, $this->stockAt($line, $this->other), 0.01);
    }

    // ── What the page reads ───────────────────────────────────────────────

    public function test_items_from_a_pallet_reports_what_it_brought_in(): void
    {
        $line = $this->receivedLine(unitCost: 100, cases: 2, perCase: 5);

        $rows = $this->service->itemsFrom($this->pallet->fresh());

        $this->assertCount(1, $rows);
        $this->assertSame('Chrome Box', $rows[0]['name']);
        $this->assertSame(2, $rows[0]['received_cases']);
        $this->assertEqualsWithDelta(10.0, $rows[0]['units'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $rows[0]['line_total'], 0.01);
        $this->assertTrue($rows[0]['complete']);
    }

    public function test_a_line_never_scanned_in_is_listed_but_not_editable(): void
    {
        // It has no product to correct. Saying so beats offering a Fix button
        // that fails.
        $this->pallet->lines()->create([
            'line_number' => 1, 'description' => 'Never arrived', 'case_count' => 1,
        ]);

        $rows = $this->service->itemsFrom($this->pallet->fresh());

        $this->assertFalse($rows[0]['linked']);
        $this->assertFalse($rows[0]['complete']);
    }

    // ── Reporting what never turned up ────────────────────────────────────

    public function test_a_line_that_never_arrived_can_be_marked_short(): void
    {
        // The case the report exists for, and the one that used to throw. A
        // line staged from the packing slip whose box never came was never
        // scanned, so it has no product — and inventory_item_id was NOT NULL.
        $line = $this->pallet->lines()->create([
            'line_number' => 1, 'description' => 'Never Arrived', 'case_count' => 4, 'unit_cost' => 25,
        ]);

        \Livewire\Livewire::test(
            \App\Filament\Resources\PalletResource\Pages\ReceivePallet::class,
            ['record' => $this->pallet],
        )->call('markLineAsShort', $line->id);

        $report = \App\Models\MissingItemReport::latest('id')->first();

        $this->assertNotNull($report, 'Nothing was recorded.');
        $this->assertNull($report->inventory_item_id);
        $this->assertSame($line->id, $report->pallet_line_id);
        $this->assertSame(4, (int) $report->expected_quantity);

        // Still says which line, because "four short" on its own is not a
        // report anyone can act on.
        $this->assertSame('Never Arrived', $report->displayName());
    }

    public function test_a_partly_received_line_reports_only_the_shortfall(): void
    {
        $line = $this->receivedLine(unitCost: 100, cases: 3, perCase: 1);

        // Two of the three arrived; put one back to outstanding.
        $line->cases()->latest('id')->first()->update(['status' => 'expected', 'received_at' => null]);

        \Livewire\Livewire::test(
            \App\Filament\Resources\PalletResource\Pages\ReceivePallet::class,
            ['record' => $this->pallet],
        )->call('markLineAsShort', $line->id);

        $this->assertSame(1, (int) \App\Models\MissingItemReport::latest('id')->first()->expected_quantity);
    }

    public function test_the_receiver_defaults_to_whoever_is_signed_in(): void
    {
        // A question the app already knows the answer to is one that gets
        // skipped and left blank.
        $this->pallet->lines()->create([
            'line_number' => 1, 'description' => 'Something', 'case_count' => 1,
        ]);

        \Livewire\Livewire::test(
            \App\Filament\Resources\PalletResource\Pages\ReceivePallet::class,
            ['record' => $this->pallet],
        )->assertSet('receivedByName', auth()->user()->name);
    }

    private function stockAt(PalletLine $line, InventoryLocation $location): float
    {
        return (float) (InventoryStock::where('inventory_item_id', $line->inventory_item_id)
            ->where('inventory_location_id', $location->id)
            ->value('quantity') ?? 0);
    }
}

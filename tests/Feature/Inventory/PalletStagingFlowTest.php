<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\PalletResource\Pages\ViewPallet;
use App\Models\{InventoryItem, InventoryLocation, Pallet, PalletLine, User, Vendor};
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stage a pallet before it lands, then confirm it by scanning as it arrives.
 *
 * The list is built first — by name, since nothing is present to scan yet —
 * and each scan on arrival confirms one case against it. Confirming a case at
 * a time rather than a whole line is what lets a part delivery be described:
 * three of five in, two still outstanding.
 */
class PalletStagingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Pallet $pallet;
    private InventoryItem $item;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        // Receiving lives in the purchasing module, which the shell-phase
        // migration leaves switched off.
        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $vendor         = Vendor::create(['name' => 'Staging Vendor', 'status' => 'active']);
        $this->location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $this->item     = InventoryItem::create([
            'name' => 'Chrome Box', 'sku' => 'CHR-1', 'barcode' => '0123456789012',
            'unit_cost' => 100, 'average_cost' => 0, 'is_active' => true,
        ]);

        $this->pallet = Pallet::create([
            'vendor_id' => $vendor->id, 'reference' => 'PO-STAGE', 'status' => 'pending',
        ]);
    }

    private function page()
    {
        return Livewire::test(ViewPallet::class, ['record' => $this->pallet->id]);
    }

    public function test_an_item_can_be_staged_by_name_before_the_pallet_lands(): void
    {
        // The name is the one required field now — staging happens for items
        // that do not exist in inventory yet, so the product is optional and
        // the name is not. Picking an item from the list fills this in for you
        // in the browser; callAction bypasses that, so it is passed here.
        $this->page()->callAction('add_expected_item', [
            'name'                  => $this->item->name,
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 3,
            'quantity_per_case'     => 10,
            'unit_cost'             => 100,
        ]);

        $line = $this->pallet->lines()->firstOrFail();

        $this->assertSame($this->item->id, $line->inventory_item_id);
        $this->assertSame(3, (int) $line->case_count);

        // Stubs exist up front, so the line can be scanned against straight
        // away rather than only at a bulk receive.
        $this->assertSame(3, $line->cases()->count());
    }

    private function stageThreeCases(): PalletLine
    {
        return PalletLine::create([
            'pallet_id' => $this->pallet->id, 'line_number' => 1, 'description' => 'Chrome Box',
            'inventory_item_id' => $this->item->id, 'inventory_location_id' => $this->location->id,
            'case_count' => 3, 'quantity_per_case' => 10, 'unit_cost' => 100, 'line_status' => 'pending',
        ]);
    }

    public function test_each_scan_confirms_one_case(): void
    {
        $line = $this->stageThreeCases();

        $service = app(ReceivingService::class);

        $first = $service->receiveOneCaseByItemCode($this->pallet->fresh(), '0123456789012');
        $this->assertSame(1, $first['received']);
        $this->assertSame(3, $first['expected']);
        $this->assertFalse($first['complete']);

        $service->receiveOneCaseByItemCode($this->pallet->fresh(), 'CHR-1');
        $third = $service->receiveOneCaseByItemCode($this->pallet->fresh(), 'CHR-1');

        $this->assertSame(3, $third['received']);
        $this->assertTrue($third['complete']);
        $this->assertSame('received', $line->fresh()->line_status);
    }

    public function test_a_partial_delivery_leaves_the_rest_outstanding(): void
    {
        $line = $this->stageThreeCases();

        app(ReceivingService::class)->receiveOneCaseByItemCode($this->pallet->fresh(), 'CHR-1');

        $this->assertSame(1, $line->fresh()->receivedCases());
        $this->assertSame('pending', $line->fresh()->line_status);

        // Only what actually arrived is in stock — one case of ten.
        $this->assertEqualsWithDelta(
            10.0,
            (float) \App\Models\InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'),
            0.01,
        );
    }

    public function test_scanning_past_the_expected_count_is_refused(): void
    {
        $this->stageThreeCases();

        $service = app(ReceivingService::class);

        foreach (range(1, 3) as $ignored) {
            $service->receiveOneCaseByItemCode($this->pallet->fresh(), 'CHR-1');
        }

        $this->expectExceptionMessage('already received');

        $service->receiveOneCaseByItemCode($this->pallet->fresh(), 'CHR-1');
    }

    public function test_a_code_not_on_the_pallet_is_refused_rather_than_guessed(): void
    {
        $this->stageThreeCases();

        $this->expectExceptionMessage('Nothing on this pallet matches');

        app(ReceivingService::class)->receiveOneCaseByItemCode($this->pallet->fresh(), 'NOT-ON-IT');
    }

    public function test_the_page_shows_progress_against_what_was_staged(): void
    {
        $this->stageThreeCases();

        app(ReceivingService::class)->receiveOneCaseByItemCode($this->pallet->fresh(), 'CHR-1');

        $this->page()
            ->assertOk()
            ->assertSee('Expected Items')
            ->assertSee('1 of 3 cases confirmed')
            ->assertSee('Partial');
    }

    public function test_scanning_through_the_page_action_works(): void
    {
        $this->stageThreeCases();

        $this->page()->callAction('scan_item', ['code' => 'CHR-1']);

        $this->assertSame(1, $this->pallet->lines()->first()->receivedCases());
    }
}

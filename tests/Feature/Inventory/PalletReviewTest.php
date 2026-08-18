<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\PalletResource\Pages\ViewPallet;
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

/**
 * Receiving is where quantities and money become permanent, so it gets a look
 * first: what turned up against what was expected, what is missing, and what
 * each item will be valued at once shipping and fees are spread over it.
 *
 * A short delivery has two honest outcomes and they differ by real stock —
 * take in everything expected, or keep only what was scanned. The review is
 * where that gets decided rather than assumed.
 */
class PalletReviewTest extends TestCase
{
    use RefreshDatabase;

    private Pallet $pallet;
    private InventoryItem $item;
    private InventoryLocation $location;
    private ReceivingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReceivingService::class);

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $vendor         = Vendor::create(['name' => 'Review Vendor', 'status' => 'active']);
        $this->location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);

        // Already carrying 10 units at $8, so the projection has history to
        // blend rather than trivially equalling the incoming cost.
        $this->item = InventoryItem::create([
            'name' => 'Box', 'sku' => 'B1', 'unit_cost' => 10, 'average_cost' => 8,
            'total_units_received' => 10, 'is_active' => true,
        ]);

        $this->pallet = Pallet::create([
            'vendor_id' => $vendor->id, 'reference' => 'PO-REVIEW', 'status' => 'receiving',
            'shipping_cost' => 30, 'payment_fees' => 20,
        ]);

        $line = PalletLine::create([
            'pallet_id' => $this->pallet->id, 'line_number' => 1, 'description' => 'Box',
            'inventory_item_id' => $this->item->id, 'inventory_location_id' => $this->location->id,
            'case_count' => 5, 'quantity_per_case' => 2, 'unit_cost' => 10,
        ]);

        $this->service->generateExpectedCases($line);
    }

    private function scan(int $times): void
    {
        foreach (range(1, $times) as $ignored) {
            $this->service->receiveOneCaseByItemCode($this->pallet->fresh(), 'B1');
        }
    }

    public function test_the_review_reports_what_is_missing(): void
    {
        $this->scan(2);

        $totals = $this->service->reviewPallet($this->pallet->fresh())['totals'];

        $this->assertSame(10.0, $totals['expected_units']);
        $this->assertSame(4.0, $totals['confirmed_units']);
        $this->assertSame(6.0, $totals['short_units']);
    }

    public function test_the_review_shows_the_landed_and_projected_cost(): void
    {
        $this->scan(2);

        $line = $this->service->reviewPallet($this->pallet->fresh())['lines'][0];

        // $10 invoice plus $50 of extras over 10 expected units.
        $this->assertEqualsWithDelta(15.00, $line['landed_unit_cost'], 0.0001);

        // 10 held at $8 blended with 4 arriving at $15 is $10 flat.
        $this->assertEqualsWithDelta(10.00, $line['projected_average_cost'], 0.0001);
    }

    public function test_an_unmapped_line_blocks_receiving(): void
    {
        PalletLine::create([
            'pallet_id' => $this->pallet->id, 'line_number' => 2,
            'description' => 'Mystery box', 'case_count' => 1, 'quantity_per_case' => 1, 'unit_cost' => 1,
        ]);

        $review = $this->service->reviewPallet($this->pallet->fresh());

        $this->assertFalse($review['can_finish']);
        $this->assertStringContainsString('Mystery box', implode(' ', $review['blockers']));
    }

    public function test_reviewing_changes_nothing_on_its_own(): void
    {
        $this->scan(2);

        $before = (float) InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity');
        $avg    = (float) $this->item->fresh()->average_cost;

        $this->service->reviewPallet($this->pallet->fresh());

        $this->assertSame($before, (float) InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'));
        $this->assertSame($avg, (float) $this->item->fresh()->average_cost);
        $this->assertSame('receiving', $this->pallet->fresh()->status);
    }

    public function test_closing_short_keeps_only_what_was_scanned(): void
    {
        $this->scan(2);

        $result = $this->service->closePalletShort($this->pallet->fresh());

        $this->assertSame(2, $result['received_cases']);
        $this->assertSame(3, $result['outstanding_cases']);
        $this->assertSame('received', $this->pallet->fresh()->status);

        // Four units arrived; the six that did not are not credited.
        $this->assertEqualsWithDelta(
            4.0,
            (float) InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'),
            0.01,
        );
    }

    public function test_receiving_all_takes_in_everything_expected(): void
    {
        $this->scan(2);

        $this->service->receivePallet($this->pallet->fresh());

        // All ten units, not just the four that were scanned.
        $this->assertEqualsWithDelta(
            10.0,
            (float) InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity'),
            0.01,
        );
    }

    public function test_the_review_action_is_available_and_its_screen_reports_the_shortfall(): void
    {
        $this->scan(2);

        // Exists on the page; the action carries no form schema, only content,
        // so mounting it is not what is worth asserting here.
        Livewire::test(ViewPallet::class, ['record' => $this->pallet->id])
            ->assertActionExists('review_and_receive');

        // Rendered directly rather than asserted against the mounted page:
        // Filament builds modal content lazily, so it is not in the snapshot at
        // mount time and assertSee would pass or fail for the wrong reason.
        $html = view('filament.modals.pallet-review', [
            'review' => $this->service->reviewPallet($this->pallet->fresh()),
        ])->render();

        $this->assertStringContainsString('unaccounted for', $html);
        $this->assertStringContainsString('Close short', $html);
        // The projected average is the number worth showing before committing.
        $this->assertStringContainsString('$10.00', $html);
    }
}

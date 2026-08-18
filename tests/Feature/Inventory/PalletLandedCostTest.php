<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shipping and payment fees have to reach the cost the stock is valued at.
 *
 * They are costs of acquiring the pallet rather than of any one line, so they
 * are spread across the units it brings in — by quantity, since ten cheap
 * units and one expensive one shipped in the same box cost the same to ship.
 * Left out, every margin figure downstream is optimistic by whatever the
 * carrier and the card processor took.
 */
class PalletLandedCostTest extends TestCase
{
    use RefreshDatabase;

    private ReceivingService $service;
    private InventoryLocation $location;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReceivingService::class);
        $this->actingAs(User::factory()->create());

        $this->vendor   = Vendor::create(['name' => 'Landed Cost Vendor', 'status' => 'active']);
        $this->location = InventoryLocation::create([
            'name' => 'Landed Cost Storage', 'type' => 'main_storage', 'status' => 'active',
        ]);
    }

    private function item(string $name): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'unit_cost' => 10.00, 'average_cost' => 0, 'is_active' => true,
        ]);
    }

    private function pallet(float $shipping, float $fees): Pallet
    {
        return Pallet::create([
            'vendor_id'     => $this->vendor->id,
            'reference'     => 'PO-LANDED-' . uniqid(),
            'status'        => 'staged',
            'shipping_cost' => $shipping,
            'payment_fees'  => $fees,
        ]);
    }

    private function line(Pallet $pallet, InventoryItem $item, int $cases, int $perCase, float $unitCost): PalletLine
    {
        return PalletLine::create([
            'pallet_id'             => $pallet->id,
            'line_number'           => $pallet->lines()->count() + 1,
            'description'           => $item->name,
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => $cases,
            'quantity_per_case'     => $perCase,
            'unit_cost'             => $unitCost,
        ]);
    }

    public function test_payment_fees_reach_the_item_cost(): void
    {
        $item   = $this->item('Fees Only');
        $pallet = $this->pallet(shipping: 0, fees: 20.00);

        // 10 units at $10, plus $20 of fees = $2 a unit on top.
        $this->line($pallet, $item, cases: 1, perCase: 10, unitCost: 10.00);

        $this->service->receivePallet($pallet->fresh());

        $this->assertEqualsWithDelta(12.00, (float) $item->fresh()->average_cost, 0.0001);
    }

    public function test_shipping_and_fees_are_both_applied(): void
    {
        $item   = $this->item('Shipping And Fees');
        $pallet = $this->pallet(shipping: 30.00, fees: 20.00);

        // 10 units at $10, plus $50 across them = $5 a unit on top.
        $this->line($pallet, $item, cases: 1, perCase: 10, unitCost: 10.00);

        $this->service->receivePallet($pallet->fresh());

        $this->assertEqualsWithDelta(15.00, (float) $item->fresh()->average_cost, 0.0001);
    }

    public function test_the_extras_are_split_across_lines_by_quantity(): void
    {
        $few  = $this->item('Few Units');
        $many = $this->item('Many Units');

        $pallet = $this->pallet(shipping: 60.00, fees: 40.00);

        // 100 total units, so $100 of extras is $1 a unit regardless of which
        // line the unit belongs to.
        $this->line($pallet, $few,  cases: 1, perCase: 20, unitCost: 10.00);
        $this->line($pallet, $many, cases: 1, perCase: 80, unitCost: 10.00);

        $this->service->receivePallet($pallet->fresh());

        $this->assertEqualsWithDelta(11.00, (float) $few->fresh()->average_cost, 0.0001);
        $this->assertEqualsWithDelta(11.00, (float) $many->fresh()->average_cost, 0.0001);
    }

    public function test_a_pallet_with_no_extras_costs_exactly_the_invoice_price(): void
    {
        $item   = $this->item('No Extras');
        $pallet = $this->pallet(shipping: 0, fees: 0);

        $this->line($pallet, $item, cases: 2, perCase: 5, unitCost: 10.00);

        $this->service->receivePallet($pallet->fresh());

        $this->assertEqualsWithDelta(10.00, (float) $item->fresh()->average_cost, 0.0001);
    }

    public function test_the_model_reports_what_will_be_spread(): void
    {
        $this->assertSame(50.0, $this->pallet(shipping: 30.00, fees: 20.00)->landedCostExtras());
        $this->assertSame(0.0, $this->pallet(shipping: 0, fees: 0)->landedCostExtras());
    }
}

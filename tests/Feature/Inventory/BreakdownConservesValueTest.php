<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemContent;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\User;
use App\Services\ContainerBreakdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Breaking a case moves value; it does not create or destroy it.
 *
 * The children used to be credited at their own existing average, which broke
 * that in both directions. A child never bought separately had no average, so
 * a $1,428 case became twelve boxes worth nothing and the money left the books
 * entirely. A child that did have one was credited at that price regardless of
 * what the case cost, inventing value from nowhere.
 *
 * This is the scenario an import lands in: SKUs arrive costed at the case
 * level, get mapped to their contents, and are then broken.
 */
class BreakdownConservesValueTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->location = InventoryLocation::create([
            'name' => 'Break Room', 'type' => 'main_storage', 'status' => 'active',
        ]);
    }

    private function product(string $sku, float $averageCost, bool $container = false): InventoryItem
    {
        return InventoryItem::create([
            'sku'          => $sku,
            'name'         => $sku,
            'is_container' => $container,
            'average_cost' => $averageCost,
            'is_active'    => true,
        ]);
    }

    private function holds(InventoryItem $case, InventoryItem $child, int $per): void
    {
        InventoryItemContent::create([
            'parent_inventory_item_id' => $case->id,
            'child_inventory_item_id'  => $child->id,
            'quantity_per_parent'      => $per,
        ]);
    }

    private function stock(InventoryItem $item, float $qty): void
    {
        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => $qty,
        ]);
    }

    private function valueOnHand(InventoryItem ...$items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $qty = (float) InventoryStock::where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $this->location->id)
                ->value('quantity');

            $total += $qty * (float) $item->fresh()->average_cost;
        }

        return round($total, 2);
    }

    public function test_a_child_with_no_cost_of_its_own_inherits_the_cases(): void
    {
        $case = $this->product('CASE', 1428.00, container: true);
        $box  = $this->product('BOX', 0.00);
        $this->holds($case, $box, 12);
        $this->stock($case, 2);

        $before = $this->valueOnHand($case, $box);

        app(ContainerBreakdownService::class)->break($case->fresh(), $this->location, 1);

        $this->assertEqualsWithDelta(119.00, (float) $box->fresh()->average_cost, 0.0001);
        $this->assertEqualsWithDelta($before, $this->valueOnHand($case, $box), 0.01);
    }

    public function test_a_child_with_its_own_cost_does_not_invent_value(): void
    {
        $case = $this->product('CASE2', 1200.00, container: true);
        // Priced at $150 elsewhere, but this case only paid $100 a box.
        $box  = $this->product('BOX2', 150.00);
        $this->holds($case, $box, 12);
        $this->stock($case, 1);

        $before = $this->valueOnHand($case, $box);

        app(ContainerBreakdownService::class)->break($case->fresh(), $this->location, 1);

        $this->assertEqualsWithDelta($before, $this->valueOnHand($case, $box), 0.01);
    }

    public function test_a_mixed_case_splits_by_relative_value(): void
    {
        // One case, two different products inside at different price points.
        $case  = $this->product('MIXED', 1755.00, container: true);
        $optic = $this->product('OPTIC', 145.00);
        $select = $this->product('SELECT', 168.40);

        $this->holds($case, $optic, 8);
        $this->holds($case, $select, 4);
        $this->stock($case, 1);

        app(ContainerBreakdownService::class)->break($case->fresh(), $this->location, 1);

        $opticCost  = (float) $optic->fresh()->average_cost;
        $selectCost = (float) $select->fresh()->average_cost;

        // The whole case cost lands on the contents, and the dearer product
        // stays dearer rather than both being averaged flat.
        $this->assertEqualsWithDelta(1755.00, (8 * $opticCost) + (4 * $selectCost), 0.05);
        $this->assertGreaterThan($opticCost, $selectCost);
    }

    public function test_breaking_several_cases_scales_the_cost(): void
    {
        $case = $this->product('CASE3', 600.00, container: true);
        $box  = $this->product('BOX3', 0.00);
        $this->holds($case, $box, 10);
        $this->stock($case, 5);

        $before = $this->valueOnHand($case, $box);

        app(ContainerBreakdownService::class)->break($case->fresh(), $this->location, 3);

        // 3 cases at $600 over 30 boxes is $60 each.
        $this->assertEqualsWithDelta(60.00, (float) $box->fresh()->average_cost, 0.0001);
        $this->assertEqualsWithDelta($before, $this->valueOnHand($case, $box), 0.01);
    }

    public function test_an_uncosted_case_leaves_its_children_alone(): void
    {
        $case = $this->product('FREE', 0.00, container: true);
        $box  = $this->product('BOX4', 42.00);
        $this->holds($case, $box, 5);
        $this->stock($case, 1);

        app(ContainerBreakdownService::class)->break($case->fresh(), $this->location, 1);

        // Nothing is recorded against the case, so there is nothing honest to
        // pass on — overwriting the child's real cost with zero would be worse.
        $this->assertEqualsWithDelta(42.00, (float) $box->fresh()->average_cost, 0.0001);
    }
}

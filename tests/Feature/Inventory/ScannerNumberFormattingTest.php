<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryScanner;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Scanning a valuable item used to take the page down.
 *
 * The component handed the view display strings — number_format() had already
 * been run over the cost, the quantity and the inventory value. Two places in
 * the view then ran it again, which is harmless on "115.00" and fatal on
 * "1,725.00": a comma makes the string non-numeric, and PHP 8.4 refuses it
 * outright rather than quietly reading it as 1. So the page worked on every
 * cheap item and 500'd on exactly the ones worth looking up.
 *
 * The stock badge had the same root cause without the crash — it compared
 * "1,725" against a number, which PHP settles as a string comparison, so the
 * answer was wrong rather than loud.
 *
 * The fix is the convention, not the two call sites: the component returns
 * numbers and whatever prints them decides how they look. These tests hold that
 * line, because a value that is sometimes already formatted is a value nobody
 * downstream can use safely.
 */
class ScannerNumberFormattingTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Setting::set('enabled_admin_modules', json_encode(['inventory']));
        AdminModules::flushMemo();

        $this->location = InventoryLocation::create([
            'name' => 'Shelf A', 'type' => 'main_storage', 'status' => 'active',
        ]);
    }

    /** An item worth more than a thousand — the case that used to crash. */
    private function valuableItem(): InventoryItem
    {
        $item = InventoryItem::create([
            'name'          => 'Sealed Case',
            'sku'           => 'VAL-1',
            'barcode'       => '6942600401310',
            'unit_cost'     => 1250.00,
            'average_cost'  => 1725.50,
            'is_active'     => true,
            'reorder_level' => 2,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => 1200,
        ]);

        return $item;
    }

    private function scan(string $code): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(InventoryScanner::class)
            ->set('scanInput', $code)
            ->call('submitScan');
    }

    public function test_scanning_an_item_worth_over_a_thousand_renders(): void
    {
        $this->valuableItem();

        $this->scan('6942600401310')->assertOk();
    }

    public function test_the_numbers_reach_the_view_as_numbers(): void
    {
        $this->valuableItem();

        $result = $this->scan('6942600401310')->get('result');

        $this->assertNotNull($result, 'The scan should have found it.');

        foreach (['avg_cost', 'total_qty', 'inventory_value', 'reorder'] as $key) {
            $this->assertIsNumeric(
                $result[$key],
                "{$key} reached the view as a display string, so anything formatting it will fail.",
            );
        }
    }

    public function test_a_cheap_item_still_renders(): void
    {
        // The old code worked here by accident — "115.00" is a numeric string,
        // so the second number_format() coerced it instead of throwing. Worth
        // keeping so the fix is not judged only on the case that broke.
        $item = InventoryItem::create([
            'name' => 'Single Pack', 'sku' => 'CHEAP-1', 'barcode' => '111222333',
            'unit_cost' => 4.50, 'average_cost' => 4.25, 'is_active' => true,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => 12,
        ]);

        $this->scan('111222333')->assertOk();
    }

    public function test_the_stock_badge_compares_quantities_as_numbers(): void
    {
        // 1200 units against a reorder level of 2 is well stocked by any
        // reading. As strings, "1,200" against "3" compares character by
        // character — "1" is less than "3" — so the badge said the opposite.
        $this->valuableItem();

        $result = $this->scan('6942600401310')->get('result');

        $this->assertGreaterThan(
            $result['reorder'] * 1.5,
            $result['total_qty'],
            'The badge decides "Well Stocked" from this comparison.',
        );
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryAnalytics;
use App\Filament\Pages\InventoryReport;
use App\Filament\Pages\InventoryValueDashboard;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use App\Services\InventoryVelocityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The inventory dashboards add up stock in the database (or once in memory)
 * rather than item by item, which took half a minute on a few thousand
 * products. These pin the figures to a small catalogue worked out by hand, so
 * the faster arithmetic is held to the same answers.
 */
class InventoryDashboardFiguresTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $main;

    private InventoryLocation $back;

    /** @var array<string, InventoryItem> */
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')])->fresh());

        $this->main = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $this->back = InventoryLocation::create(['name' => 'Back', 'type' => 'main_storage', 'status' => 'active']);

        // name => [average_cost, unit_cost, reorder_level, category, [main qty, back qty]]
        foreach ([
            'Alpha'   => [10, 10, 5, 'Hobby', [3, 1]],
            'Beta'    => [0, 4, null, 'Hobby', [10, null]],
            'Gamma'   => [50, 50, 1, 'Retail', [2, null]],
            'Deleted' => [20, 20, null, 'Retail', [5, null]],
            'Delta'   => [2, 2, 0, null, [null, 0]],
        ] as $name => [$avg, $unit, $reorder, $category, [$atMain, $atBack]]) {
            $item = InventoryItem::create([
                'name' => $name, 'sku' => strtoupper($name), 'average_cost' => $avg, 'unit_cost' => $unit,
                'reorder_level' => $reorder, 'category' => $category, 'is_active' => true,
            ]);
            foreach ([[$this->main, $atMain], [$this->back, $atBack]] as [$location, $qty]) {
                if ($qty !== null) {
                    InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => $qty]);
                }
            }
            $this->items[$name] = $item;
        }

        $this->items['Deleted']->delete();
    }

    public function test_analytics_figures(): void
    {
        $page = new InventoryAnalytics();

        // Beta has no average cost, so it carries no value here; the deleted
        // item counts for nothing.
        $summary = $page->getSummary();
        $this->assertEqualsWithDelta(140.0, $summary['total_value'], 0.01);
        $this->assertEqualsWithDelta(16.0, $summary['total_units'], 0.01);

        $this->assertSame(['Alpha', 'Delta'], array_column($page->getLowStockItems(), 'name'));
        $this->assertSame(['low_stock', 'out_of_stock'], array_column($page->getLowStockItems(), 'status'));

        $locations = collect($page->getLocationHealth())->keyBy('name');
        $this->assertSame(['Main', 'Back'], $locations->keys()->all());
        $this->assertSame([15, 3], [$locations['Main']['total_units'], $locations['Main']['unique_items']]);
        $this->assertEqualsWithDelta(130.0, $locations['Main']['value'], 0.01);
        $this->assertSame([1, 2], [$locations['Back']['total_units'], $locations['Back']['unique_items']]);

        $fast = $page->getFastMovers();
        $this->assertCount(5, $fast);
        $this->assertSame(['Gamma', 'Main'], [$fast[0]['name'], $fast[0]['location']]);
        $this->assertEqualsWithDelta(100.0, $fast[0]['value'], 0.01);

        $this->assertSame(['Alpha'], array_column($page->getDeadStock(), 'name'));
    }

    public function test_value_dashboard_figures(): void
    {
        $vendorA = Vendor::create(['name' => 'Vendor A', 'status' => 'active']);
        $vendorB = Vendor::create(['name' => 'Vendor B', 'status' => 'active']);
        $line = function (Vendor $vendor, InventoryItem $item, float $cost, bool $archived = false) {
            static $n = 0;
            $pallet = Pallet::create(['vendor_id' => $vendor->id, 'reference' => 'PO-' . ++$n, 'status' => 'pending']);
            PalletLine::create([
                'pallet_id' => $pallet->id, 'line_number' => 1, 'description' => $item->name,
                'inventory_item_id' => $item->id, 'inventory_location_id' => $this->main->id,
                'case_count' => 1, 'quantity_per_case' => 10, 'unit_cost' => $cost,
            ]);
            if ($archived) $pallet->delete();
        };
        // Alpha: one vendor charged double the other — a high-variance item.
        $line($vendorA, $this->items['Alpha'], 10);
        $line($vendorB, $this->items['Alpha'], 20);
        // Gamma: the wild price is on an archived pallet, which doesn't count.
        $line($vendorA, $this->items['Gamma'], 50);
        $line($vendorB, $this->items['Gamma'], 1000, archived: true);

        $page = new InventoryValueDashboard();

        // Beta is valued at its list cost when there's no average.
        $this->assertEqualsWithDelta(180.0, $page->getTotalInventoryValueProperty(), 0.01);
        $this->assertSame('Gamma', $page->getTopItemsProperty()[0]['name']);
        $this->assertSame(['Alpha', 'Delta'], array_column($page->getLowStockHighValueProperty(), 'name'));

        $categories = collect($page->getCategoryBreakdownProperty())->keyBy('category');
        $this->assertEqualsWithDelta(80.0, $categories['Hobby']['value'], 0.01);
        $this->assertEqualsWithDelta(14.0, $categories['Hobby']['qty'], 0.01);
        $this->assertSame('Retail', $page->getCategoryBreakdownProperty()[0]['category']);

        $locations = collect($page->getValueByLocationProperty())->keyBy('name');
        $this->assertSame(3, $locations['Main']['count']);
        $this->assertEqualsWithDelta(180.0, $locations['Main']['value'], 0.01);
        $this->assertSame(2, $locations['Back']['count']);

        $health = $page->getInventoryHealthProperty();
        $this->assertSame(4, $health['total_items']);
        $this->assertEqualsWithDelta(16.0, $health['total_units'], 0.01);
        $this->assertSame(2, $health['low_stock_count']);
        $this->assertSame(1, $health['no_cost_count']);
        $this->assertSame(1, $health['high_variance_count']);
    }

    public function test_bulk_velocity_ranking_matches_the_one_item_calculation(): void
    {
        foreach ([['Alpha', -6, 'show_sale', 2], ['Alpha', 4, 'opening', 5], ['Gamma', -1, 'show_sale', 1], ['Gamma', 9, 'adjustment', 1], ['Beta', 3, 'show_sale', 45]] as [$name, $qty, $type, $daysAgo]) {
            $movement = InventoryMovement::create([
                'inventory_item_id' => $this->items[$name]->id, 'to_location_id' => $this->main->id,
                'quantity' => $qty, 'movement_type' => $type, 'reason' => 'test',
            ]);
            $movement->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }

        $service = new InventoryVelocityService();
        $ranked = collect($service->getRankedByVelocity(30))->keyBy('item_id');

        foreach (['Alpha', 'Beta', 'Gamma', 'Delta'] as $name) {
            $item = InventoryItem::find($this->items[$name]->id);
            $this->assertEquals($service->calculateItemVelocity($item, 30), $ranked[$item->id], "{$name} differs");
        }
        $this->assertFalse($ranked->has($this->items['Deleted']->id));
    }

    public function test_aging_buckets_count_days_forwards(): void
    {
        InventoryLot::create([
            'product_id' => $this->items['Alpha']->id, 'quantity' => 4, 'remaining_quantity' => 4,
            'unit_cost' => 10, 'status' => 'active', 'source' => 'received', 'received_at' => now()->subDays(45),
        ]);

        $page = new InventoryReport();

        // Carbon 3 diffs are signed; counting backwards put everything in 0-30.
        $aging = $page->getAgingInventoryProperty();
        $this->assertSame(['Alpha'], array_column(collect($aging)->firstWhere('label', '31-60 days')['items'], 'name'));

        $lots = $page->getLotAgingProperty();
        $this->assertSame(1, $lots['aging_30']['count']);
        $this->assertSame('Alpha', $lots['aging_30']['items'][0]['item_name']);
    }
}

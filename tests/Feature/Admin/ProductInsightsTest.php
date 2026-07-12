<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ProductInsights;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductInsightsTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;
    private InventoryLocation $loc;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        $u = User::factory()->create();
        $this->actingAs($u);
        $this->show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $u->id]);
        $this->loc  = InventoryLocation::create(['name' => 'Main']);
    }

    private function stock(InventoryItem $item, float $qty): void
    {
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $this->loc->id, 'quantity' => $qty]);
    }

    private function sale(InventoryItem $item, int $qty, float $unitCost, float $totalPrice, ?string $when = null): void
    {
        WhatnotShowOrder::create([
            'show_id' => $this->show->id, 'inventory_item_id' => $item->id, 'buyer_username' => 'b',
            'item_name' => $item->name, 'quantity' => $qty, 'unit_cost' => $unitCost,
            'total_price' => $totalPrice, 'status' => 'completed',
            'show_date' => $when ?? now()->toDateString(),
        ]);
    }

    private function rowFor(int $itemId): ?array
    {
        return (new ProductInsights)->getRowsProperty()->firstWhere('id', $itemId);
    }

    public function test_computes_margin_and_sell_through(): void
    {
        $item = InventoryItem::create(['name' => 'Hot Box', 'unit_cost' => 5, 'is_active' => true]);
        $this->stock($item, 10);                    // 10 on hand
        $this->sale($item, 2, 5, 20);               // sold 2, cost 10, revenue 20
        $this->sale($item, 2, 5, 20);               // sold 2, cost 10, revenue 20

        $row = $this->rowFor($item->id);

        $this->assertEquals(4, $row['units_sold']);
        $this->assertEqualsWithDelta(40, $row['revenue'], 0.01);
        $this->assertEqualsWithDelta(20, $row['margin'], 0.01);   // 40 revenue − 20 cogs
        $this->assertEqualsWithDelta(50.0, $row['margin_pct'], 0.1); // 20/40
        // sell-through: 4 sold / (4 + 10 on hand) = 28.6%
        $this->assertEqualsWithDelta(28.6, $row['sell_through'], 0.1);
        $this->assertFalse($row['is_dead']);
    }

    public function test_flags_dead_stock_and_never_sold(): void
    {
        $dead = InventoryItem::create(['name' => 'Old Case', 'unit_cost' => 10, 'is_active' => true]);
        $this->stock($dead, 3);                       // $30 capital
        $this->sale($dead, 1, 10, 12, now()->subDays(120)->toDateString()); // last sold 120d ago

        $never = InventoryItem::create(['name' => 'Never', 'unit_cost' => 4, 'is_active' => true]);
        $this->stock($never, 5);                      // never sold, on hand

        $deadRow  = $this->rowFor($dead->id);
        $neverRow = $this->rowFor($never->id);

        $this->assertTrue($deadRow['is_dead']);
        $this->assertTrue($neverRow['is_dead']);      // no sale + on hand = dead
        $this->assertTrue($neverRow['never_sold']);
        $this->assertEqualsWithDelta(0.0, $neverRow['sell_through'], 0.01); // 0 sold / (0 + 5 stock) = 0%
    }

    public function test_flags_reorder_for_fast_sellers_running_low(): void
    {
        // Sold 9, only 1 left → 90% sell-through, well over the 70% reorder line.
        $hot = InventoryItem::create(['name' => 'Fast Mover', 'unit_cost' => 5, 'is_active' => true]);
        $this->stock($hot, 1);
        $this->sale($hot, 9, 5, 90);

        // Sold 2, 10 left → 16.7% sell-through, plenty of stock, no reorder.
        $slow = InventoryItem::create(['name' => 'Slow Mover', 'unit_cost' => 5, 'is_active' => true]);
        $this->stock($slow, 10);
        $this->sale($slow, 2, 5, 20);

        $this->assertTrue($this->rowFor($hot->id)['needs_reorder']);
        $this->assertFalse($this->rowFor($slow->id)['needs_reorder']);

        // The reorder view shows only the fast mover.
        $reorder = (new ProductInsights);
        $reorder->view = 'reorder';
        $ids = $reorder->getRowsProperty()->pluck('id')->all();
        $this->assertContains($hot->id, $ids);
        $this->assertNotContains($slow->id, $ids);
    }

    public function test_dead_stock_view_and_kpis(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'a@test.com']);
        $admin->assignRole('admin');
        config(['app.owner_email' => 'owner@x.com']);

        $dead = InventoryItem::create(['name' => 'Dead Weight', 'unit_cost' => 10, 'is_active' => true]);
        $this->stock($dead, 4); // $40 dead capital, never sold

        Livewire::actingAs($admin);

        Livewire::test(ProductInsights::class)
            ->set('view', 'dead_stock')
            ->assertSee('Dead Weight')
            ->assertSee('$40'); // inventory value / dead value KPI
    }
}

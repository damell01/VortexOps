<?php

namespace Tests\Feature\Admin;

use App\AI\Contracts\AIProvider;
use App\Filament\Pages\ProductInsights;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WhatnotShowOrder;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    /**
     * test_summarize_button_populates_the_narrative() narrows
     * enabled_admin_modules; Setting::set() forgets the cache key at write
     * time, but RefreshDatabase doesn't roll the cache store back, so that
     * narrowed value would otherwise leak into whichever test runs next in
     * this PHPUnit process. Forget it again post-test.
     */
    protected function tearDown(): void
    {
        Cache::forget('setting:enabled_admin_modules');
        AdminModules::flushMemo();
        parent::tearDown();
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

    public function test_suggested_reorder_qty_uses_trailing_velocity_and_default_lead_time(): void
    {
        $item = InventoryItem::create(['name' => 'Steady Seller', 'unit_cost' => 5, 'is_active' => true]);
        $this->stock($item, 5);
        // 30 units sold across the trailing 30-day window → 1 unit/day velocity.
        $this->sale($item, 30, 5, 300);

        $row = $this->rowFor($item->id);

        $this->assertEqualsWithDelta(1.0, $row['velocity'], 0.01);
        $this->assertEquals(14, $row['lead_time_days']); // no vendor → default
        // reorder point = 1/day * (14 + 7 buffer) = 21; on hand 5 → suggest 16.
        $this->assertEquals(16, $row['suggested_reorder_qty']);
        $this->assertEqualsWithDelta(5.0, $row['days_of_stock_remaining'], 0.01);
    }

    public function test_suggested_reorder_qty_uses_vendor_lead_time_when_set(): void
    {
        $vendor = Vendor::create(['name' => 'Slow Vendor', 'status' => 'active', 'lead_time_days' => 30]);
        $item = InventoryItem::create(['name' => 'Vendor Sourced', 'unit_cost' => 5, 'is_active' => true, 'preferred_vendor_id' => $vendor->id]);
        $this->stock($item, 5);
        $this->sale($item, 30, 5, 300); // 1/day velocity

        $row = $this->rowFor($item->id);

        $this->assertEquals(30, $row['lead_time_days']);
        // reorder point = 1/day * (30 + 7) = 37; on hand 5 → suggest 32.
        $this->assertEquals(32, $row['suggested_reorder_qty']);
    }

    public function test_suggested_reorder_qty_null_when_no_recent_sales(): void
    {
        $item = InventoryItem::create(['name' => 'Stale Velocity', 'unit_cost' => 5, 'is_active' => true]);
        $this->stock($item, 5);
        // Sold plenty, but outside the 30-day velocity window — no recent pace to project from.
        $this->sale($item, 20, 5, 200, now()->subDays(40)->toDateString());

        $row = $this->rowFor($item->id);

        $this->assertEqualsWithDelta(0.0, $row['velocity'], 0.01);
        $this->assertNull($row['suggested_reorder_qty']);
        $this->assertNull($row['days_of_stock_remaining']);
    }

    public function test_suggested_reorder_qty_null_when_stock_already_covers_demand(): void
    {
        $item = InventoryItem::create(['name' => 'Well Stocked', 'unit_cost' => 5, 'is_active' => true]);
        $this->stock($item, 100); // way more than any reasonable reorder point
        $this->sale($item, 30, 5, 300); // 1/day velocity

        $row = $this->rowFor($item->id);

        $this->assertNull($row['suggested_reorder_qty']);
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

    public function test_summarize_button_populates_the_narrative(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams', 'inventory', 'ai']));
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'summarizer@test.com']);
        $admin->assignRole('admin');
        config(['app.owner_email' => 'owner@test.com']);

        $item = InventoryItem::create(['name' => 'Summarized Item', 'unit_cost' => 5, 'is_active' => true]);
        $this->stock($item, 10);
        $this->sale($item, 2, 5, 20);

        $fake = new class implements AIProvider {
            public function name(): string { return 'fake'; }
            public function chat(array $m, string $model, array $o = []): string { return 'Catalogue is healthy.'; }
            public function stream(array $m, string $model, array $o = []): \Generator { yield ''; }
            public function vision(string $p, string $i, string $model, array $o = []): string { return ''; }
            public function embed(string $t, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return true; }
        };
        $this->app->instance(AIProvider::class, $fake);

        Livewire::actingAs($admin);
        Livewire::test(ProductInsights::class)
            ->assertSee('Summarize catalogue')
            ->call('generateNarrative')
            ->assertSet('narrative', 'Catalogue is healthy.')
            ->assertSee('Catalogue is healthy.');
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryScanner;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryScannerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private InventoryItem $item;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        Setting::set('enabled_admin_modules', json_encode(['inventory']));
        AdminModules::flushMemo();

        $this->location = InventoryLocation::create([
            'name'   => 'Shelf A',
            'type'   => 'main_storage',
            'status' => 'active',
        ]);

        $this->item = InventoryItem::create([
            'name'         => 'Topps Chrome Hobby Box',
            'sku'          => 'TCH-2024',
            'barcode'      => '012345678901',
            'unit_cost'    => 120.00,
            'average_cost' => 115.00,
            'is_active'    => true,
        ]);
    }

    // ── InventoryItem::findByScan ──────────────────────────────────────────────

    public function test_find_by_scan_finds_by_barcode(): void
    {
        $found = InventoryItem::findByScan('012345678901');

        $this->assertNotNull($found);
        $this->assertEquals($this->item->id, $found->id);
    }

    public function test_find_by_scan_falls_back_to_sku(): void
    {
        $found = InventoryItem::findByScan('TCH-2024');

        $this->assertNotNull($found);
        $this->assertEquals($this->item->id, $found->id);
    }

    public function test_find_by_scan_prefers_barcode_over_sku(): void
    {
        // Another item whose SKU matches the first item's barcode — barcode should win
        InventoryItem::create([
            'name'         => 'Other Item',
            'sku'          => '012345678901',
            'average_cost' => 0,
            'unit_cost'    => 0,
            'is_active'    => true,
        ]);

        $found = InventoryItem::findByScan('012345678901');

        $this->assertEquals($this->item->id, $found->id);
    }

    public function test_find_by_scan_returns_null_for_unknown_code(): void
    {
        $this->assertNull(InventoryItem::findByScan('UNKNOWN-9999'));
    }

    // ── canAccess ─────────────────────────────────────────────────────────────

    public function test_can_access_returns_true_for_admin_with_module_enabled(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(InventoryScanner::canAccess());
    }

    public function test_can_access_returns_false_for_non_admin(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(InventoryScanner::canAccess());
    }

    public function test_can_access_returns_false_when_inventory_module_disabled(): void
    {
        Setting::set('enabled_admin_modules', json_encode([]));
        AdminModules::flushMemo();
        $this->actingAs($this->admin);

        $this->assertFalse(InventoryScanner::canAccess());
    }

    // ── submitScan ────────────────────────────────────────────────────────────

    public function test_submit_scan_populates_result_on_barcode_hit(): void
    {
        $this->actingAs($this->admin);
        InventoryStock::create([
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => 5,
        ]);

        $page = new InventoryScanner();
        $page->scanInput = '012345678901';
        $page->submitScan();

        $this->assertNotNull($page->result);
        $this->assertEquals($this->item->id, $page->result['id']);
        $this->assertEquals('Topps Chrome Hobby Box', $page->result['name']);
        $this->assertEquals('5', $page->result['total_qty']);
        $this->assertCount(1, $page->result['stock']);
        $this->assertEquals('Shelf A', $page->result['stock'][0]['location']);
        $this->assertEquals(5.0, $page->result['stock'][0]['qty']);
        $this->assertEmpty($page->scanInput);
        $this->assertNull($page->errorMessage);
    }

    public function test_submit_scan_populates_result_on_sku_hit(): void
    {
        $this->actingAs($this->admin);

        $page = new InventoryScanner();
        $page->scanInput = 'TCH-2024';
        $page->submitScan();

        $this->assertNotNull($page->result);
        $this->assertEquals('TCH-2024', $page->result['sku']);
    }

    public function test_submit_scan_sets_error_for_unknown_code(): void
    {
        $this->actingAs($this->admin);

        $page = new InventoryScanner();
        $page->scanInput = 'NOSUCHITEM';
        $page->submitScan();

        $this->assertNull($page->result);
        $this->assertStringContainsString('NOSUCHITEM', $page->errorMessage);
    }

    public function test_submit_scan_ignores_whitespace_only_input(): void
    {
        $this->actingAs($this->admin);

        $page = new InventoryScanner();
        $page->scanInput = '   ';
        $page->submitScan();

        $this->assertNull($page->result);
        $this->assertNull($page->errorMessage);
    }

    public function test_submit_scan_clears_previous_error(): void
    {
        $this->actingAs($this->admin);

        $page = new InventoryScanner();
        $page->errorMessage = 'Old error';
        $page->scanInput    = '012345678901';
        $page->submitScan();

        $this->assertNull($page->errorMessage);
        $this->assertNotNull($page->result);
    }

    public function test_submit_scan_includes_recent_movements(): void
    {
        $this->actingAs($this->admin);
        InventoryMovement::create([
            'inventory_item_id' => $this->item->id,
            'movement_type'     => 'opening',
            'quantity'          => 10,
            'to_location_id'    => $this->location->id,
            'created_by'        => $this->admin->id,
        ]);

        $page = new InventoryScanner();
        $page->scanInput = 'TCH-2024';
        $page->submitScan();

        $this->assertCount(1, $page->result['movements']);
        $this->assertEquals('opening', $page->result['movements'][0]['type']);
        $this->assertEquals(10.0, $page->result['movements'][0]['qty']);
    }

    // ── applyAdjust ───────────────────────────────────────────────────────────

    public function test_apply_adjust_creates_stock_and_logs_movement(): void
    {
        $this->actingAs($this->admin);

        $page = new InventoryScanner();
        $page->scanInput = '012345678901';
        $page->submitScan();

        $page->adjustLocationId = $this->location->id;
        $page->adjustQty        = '10';
        $page->adjustReason     = 'Manual count';
        $page->applyAdjust();

        $stock = InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('inventory_location_id', $this->location->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(10.0, (float) $stock->quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $this->item->id,
            'movement_type'     => 'adjustment',
            'quantity'          => 10,
            'to_location_id'    => $this->location->id,
        ]);
    }

    public function test_apply_adjust_uses_adjustment_out_for_negative_quantity(): void
    {
        $this->actingAs($this->admin);
        InventoryStock::create([
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => 20,
        ]);

        $page = new InventoryScanner();
        $page->scanInput = 'TCH-2024';
        $page->submitScan();

        $page->adjustLocationId = $this->location->id;
        $page->adjustQty        = '-5';
        $page->applyAdjust();

        $this->assertEquals(
            15.0,
            (float) InventoryStock::where('inventory_item_id', $this->item->id)->value('quantity')
        );

        // Absolute quantity plus a direction, which is the convention every
        // other movement in the app uses and the one signedChange() reads.
        // This screen used to store a negative quantity instead — two
        // conventions for one column is how the log ended up reporting a
        // reduction of twelve as "+12".
        $movement = \App\Models\InventoryMovement::latest('id')->first();

        $this->assertSame('adjustment', $movement->movement_type);
        $this->assertEqualsWithDelta(5.0, (float) $movement->quantity, 0.01);
        $this->assertEqualsWithDelta(-5.0, $movement->signedChange(), 0.01);
        $this->assertSame($this->location->id, $movement->from_location_id);
        $this->assertNull($movement->to_location_id);

        // The levels either side, which the hand-written version never wrote.
        $this->assertEqualsWithDelta(20.0, (float) $movement->quantity_before, 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $movement->quantity_after, 0.01);
    }

    public function test_apply_adjust_refreshes_result_after_adjustment(): void
    {
        $this->actingAs($this->admin);

        $page = new InventoryScanner();
        $page->scanInput = '012345678901';
        $page->submitScan();

        $page->adjustLocationId = $this->location->id;
        $page->adjustQty        = '7';
        $page->applyAdjust();

        // Page re-scans after adjust — result reflects new quantity
        $this->assertNotNull($page->result);
        $this->assertEquals('7', $page->result['total_qty']);
        $this->assertFalse($page->adjustMode);
    }

    public function test_apply_adjust_uses_default_reason_when_blank(): void
    {
        $this->actingAs($this->admin);

        $page = new InventoryScanner();
        $page->scanInput = '012345678901';
        $page->submitScan();

        $page->adjustLocationId = $this->location->id;
        $page->adjustQty        = '3';
        $page->adjustReason     = '';
        $page->applyAdjust();

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $this->item->id,
            'reason'            => 'Manual scanner adjustment',
        ]);
    }
}

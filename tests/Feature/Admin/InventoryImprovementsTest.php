<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\InventoryLocationResource;
use App\Filament\Resources\InventoryMovementResource\Pages\ListInventoryMovements;
use App\Filament\Resources\InventoryStockResource\Pages\ListInventoryStock;
use App\Filament\Resources\ProductIdentityResource\Pages\ListProductIdentities;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\ProductIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the inventory-side pass: low-stock filter on Stock Levels, delete
 * protection + CSV export on Locations, date-range filter on the Movement
 * Log, and bulk alias reassignment on the Alias Manager.
 */
class InventoryImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        foreach (['admin', 'super_admin', 'streamer', 'fulfillment', 'fulfillment_admin'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');

        return $u;
    }

    private function item(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'sku' => 'SKU-' . uniqid(),
            'name' => 'Test Item',
            'unit_cost' => 10,
            'reorder_level' => 5,
            'is_active' => true,
        ], $overrides));
    }

    private function location(array $overrides = []): InventoryLocation
    {
        return InventoryLocation::create(array_merge([
            'name' => 'Loc-' . uniqid(),
            'type' => 'main_storage',
            'status' => 'active',
        ], $overrides));
    }

    // ── Stock Levels: low-stock filter ──────────────────────────────────────

    public function test_low_stock_filter_isolates_rows_at_or_below_reorder_level(): void
    {
        $this->actingAs($this->admin());

        $lowItem = $this->item(['reorder_level' => 10]);
        $okItem = $this->item(['reorder_level' => 10]);
        $loc = $this->location();

        InventoryStock::create(['inventory_item_id' => $lowItem->id, 'inventory_location_id' => $loc->id, 'quantity' => 5]);
        InventoryStock::create(['inventory_item_id' => $okItem->id, 'inventory_location_id' => $loc->id, 'quantity' => 50]);

        Livewire::test(ListInventoryStock::class)
            ->loadTable()
            ->assertCanSeeTableRecords(InventoryStock::all())
            ->filterTable('low_stock')
            ->assertCanSeeTableRecords(InventoryStock::where('inventory_item_id', $lowItem->id)->get())
            ->assertCanNotSeeTableRecords(InventoryStock::where('inventory_item_id', $okItem->id)->get());
    }

    // ── Locations: delete protection ────────────────────────────────────────

    public function test_location_with_stock_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());

        $loc = $this->location();
        InventoryStock::create(['inventory_item_id' => $this->item()->id, 'inventory_location_id' => $loc->id, 'quantity' => 3]);

        $this->assertFalse(InventoryLocationResource::canDelete($loc));
    }

    public function test_location_referenced_by_deduction_request_line_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());

        $creator = User::factory()->create();
        $show = \App\Models\Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        $streamer = \App\Models\Streamer::create(['name' => 'S', 'status' => 'active']);
        $request = DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);

        $loc = $this->location();
        DeductionRequestLine::create([
            'deduction_request_id' => $request->id,
            'inventory_item_id' => $this->item()->id,
            'inventory_location_id' => $loc->id,
            'quantity_suggested' => 1,
            'quantity_approved' => 1,
            'unit_cost_snapshot' => 1,
            'line_total' => 1,
        ]);

        $this->assertFalse(InventoryLocationResource::canDelete($loc));
    }

    public function test_empty_location_can_be_deleted_by_admin(): void
    {
        $this->actingAs($this->admin());

        $loc = $this->location();

        $this->assertTrue(InventoryLocationResource::canDelete($loc));
    }

    public function test_location_with_zero_quantity_stock_row_can_still_be_deleted(): void
    {
        $this->actingAs($this->admin());

        $loc = $this->location();
        InventoryStock::create(['inventory_item_id' => $this->item()->id, 'inventory_location_id' => $loc->id, 'quantity' => 0]);

        $this->assertTrue(InventoryLocationResource::canDelete($loc));
    }

    public function test_non_admin_cannot_delete_a_location(): void
    {
        $streamerUser = User::factory()->create();
        $streamerUser->assignRole('streamer');
        $this->actingAs($streamerUser);

        $this->assertFalse(InventoryLocationResource::canDelete($this->location()));
    }

    public function test_locations_export_csv_route_succeeds_for_admin(): void
    {
        $this->actingAs($this->admin());
        $this->location();

        $this->get(route('export.locations'))->assertOk();
    }

    // ── Stock Levels export bug fix ─────────────────────────────────────────

    public function test_stock_levels_export_csv_route_succeeds_for_admin(): void
    {
        $this->actingAs($this->admin());
        $loc = $this->location();
        InventoryStock::create(['inventory_item_id' => $this->item()->id, 'inventory_location_id' => $loc->id, 'quantity' => 5]);

        $this->get(route('export.stock-levels'))->assertOk();
    }

    // ── Movement Log: date-range filter ─────────────────────────────────────

    public function test_movement_log_date_range_filter_excludes_out_of_range_rows(): void
    {
        $this->actingAs($this->admin());

        // created_at isn't fillable on InventoryMovement — force it after create()
        // so the two rows fall on opposite sides of the filter window.
        $item = $this->item();
        $inRange = InventoryMovement::create(['inventory_item_id' => $item->id, 'quantity' => 1, 'movement_type' => 'opening']);
        $inRange->forceFill(['created_at' => now()->subDays(2)])->save();
        $outOfRange = InventoryMovement::create(['inventory_item_id' => $item->id, 'quantity' => 1, 'movement_type' => 'opening']);
        $outOfRange->forceFill(['created_at' => now()->subDays(30)])->save();

        Livewire::test(ListInventoryMovements::class)
            ->loadTable()
            ->filterTable('date_range', ['from' => now()->subDays(5)->toDateString(), 'until' => now()->toDateString()])
            ->assertCanSeeTableRecords([$inRange])
            ->assertCanNotSeeTableRecords([$outOfRange]);
    }

    // ── Alias Manager: bulk reassign ────────────────────────────────────────

    public function test_bulk_reassign_moves_selected_aliases_to_the_chosen_product(): void
    {
        $this->actingAs($this->admin());

        $originalProduct = $this->item();
        $targetProduct = $this->item();

        $alias1 = ProductIdentity::create(['product_id' => $originalProduct->id, 'type' => ProductIdentity::TYPE_ALIAS, 'value' => 'alias one']);
        $alias2 = ProductIdentity::create(['product_id' => $originalProduct->id, 'type' => ProductIdentity::TYPE_ALIAS, 'value' => 'alias two']);

        Livewire::test(ListProductIdentities::class)
            ->callTableBulkAction('reassign_to_product', [$alias1->id, $alias2->id], ['product_id' => $targetProduct->id]);

        $this->assertEquals($targetProduct->id, $alias1->fresh()->product_id);
        $this->assertEquals($targetProduct->id, $alias2->fresh()->product_id);
    }
}

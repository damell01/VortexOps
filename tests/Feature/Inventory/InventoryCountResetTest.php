<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\User;
use App\Services\InventoryCountReset;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryCountResetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private InventoryLocation $a;
    private InventoryLocation $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->enableAdminModules();
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($this->admin);
        $this->a = InventoryLocation::create(['name' => 'Location A', 'status' => 'active']);
        $this->b = InventoryLocation::create(['name' => 'Location B', 'status' => 'active']);
        Queue::fake();
    }
    private function item(string $name, float $quantity, bool $active = true): InventoryItem
    {
        $item = InventoryItem::create(['name' => $name, 'unit_cost' => 8, 'average_cost' => 10, 'sale_price' => 20, 'is_active' => $active]);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $this->a->id, 'quantity' => $quantity]);
        return $item;
    }
    public function test_admin_can_reset_all_counts_preserving_catalog_prices_receipts_and_logging_each_change(): void
    {
        $item = $this->item('Box', 5);
        $inactive = $this->item('Inactive box', 2, false);
        $negative = $this->item('Incorrect count', -2);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $this->b->id, 'quantity' => 3]);
        $lot = InventoryLot::create(['product_id' => $item->id, 'quantity' => 12, 'remaining_quantity' => 12, 'unit_cost' => 10, 'status' => 'active', 'received_at' => now()]);
        $result = app(InventoryCountReset::class)->reset($this->admin, 'RESET ALL STOCK', 'Starting a new stock count');
        $this->assertSame(4, $result['rows']);
        $this->assertEquals(0, InventoryStock::sum('quantity'));
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->assertEquals(0, $lot->fresh()->remaining_quantity);
        $this->assertSame('depleted', $lot->fresh()->status);
        $this->assertEquals(12, $lot->fresh()->quantity);
        $this->assertEquals(10, $item->fresh()->average_cost);
        $this->assertEquals(8, $item->fresh()->unit_cost);
        $this->assertEquals(20, $item->fresh()->sale_price);
        $this->assertFalse($inactive->fresh()->is_active);
        $this->assertDatabaseCount('products', 3);
        $this->assertSame($this->admin->id, InventoryMovement::first()->created_by);
        $audit = \Spatie\Activitylog\Models\Activity::where('description', 'All inventory counts reset to zero')->firstOrFail();
        $this->assertCount(4, $audit->properties['stock_before']);
        Queue::assertNothingPushed();
    }
    public function test_selected_mode_only_resets_selected_products_in_all_their_locations(): void
    {
        $selected = $this->item('Selected', 5);
        $other = $this->item('Other', 7);
        InventoryStock::create(['inventory_item_id' => $selected->id, 'inventory_location_id' => $this->b->id, 'quantity' => 2]);
        app(InventoryCountReset::class)->reset($this->admin, 'RESET SELECTED STOCK', 'Recount selected box', [$selected->id]);
        $this->assertEquals(0, $selected->stock()->sum('quantity'));
        $this->assertEquals(7, $other->stock()->sum('quantity'));
        $this->assertDatabaseCount('inventory_movements', 2);
    }
    public function test_canceling_or_failing_confirmation_leaves_stock_unchanged(): void
    {
        $item = $this->item('Keep stock', 5);
        Livewire::test(ListInventoryItems::class)->mountAction('resetAllCounts')->unmountAction();
        Livewire::test(ListInventoryItems::class)->mountAction('resetAllCounts')->setActionData(['confirmation' => 'RESET', 'reason' => 'Count'])->callMountedAction()->assertHasActionErrors(['confirmation']);
        $this->assertEquals(5, $item->stock()->sum('quantity'));
        $this->assertDatabaseCount('inventory_movements', 0);
    }
    public function test_selected_card_action_freezes_selection_when_confirmation_opens(): void
    {
        $selected = $this->item('First', 5);
        $other = $this->item('Second', 7);
        Livewire::test(ListInventoryItems::class)->set('selectedItems', [(string) $selected->id])->mountAction('resetSelectedCounts')
            ->set('selectedItems', [(string) $other->id])->setActionData(['confirmation' => 'RESET SELECTED STOCK', 'reason' => 'Recount first box'])
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertEquals(0, $selected->stock()->sum('quantity'));
        $this->assertEquals(7, $other->stock()->sum('quantity'));
    }
    public function test_select_all_matching_only_resets_the_matching_products(): void
    {
        $matched = $this->item('Match box', 5);
        $other = $this->item('Other box', 7);
        Livewire::test(ListInventoryItems::class)->set('catalogSearch', 'Match')->call('selectAllMatchingItems')->mountAction('resetSelectedCounts')
            ->setActionData(['confirmation' => 'RESET SELECTED STOCK', 'reason' => 'Recount matching products'])->callMountedAction()->assertHasNoActionErrors();
        $this->assertEquals(0, $matched->stock()->sum('quantity'));
        $this->assertEquals(7, $other->stock()->sum('quantity'));
    }
    public function test_table_selection_has_the_same_reset_behavior(): void
    {
        $matched = $this->item('Selected table row', 5);
        $other = $this->item('Other table row', 7);
        Livewire::test(ListInventoryItems::class)->callTableBulkAction('reset_selected_stock', [$matched], ['confirmation' => 'RESET SELECTED STOCK', 'reason' => 'Recount selected row'])->assertHasNoActionErrors();
        $this->assertEquals(0, $matched->stock()->sum('quantity'));
        $this->assertEquals(7, $other->stock()->sum('quantity'));
    }

    public function test_empty_selection_cannot_fall_back_to_resetting_all_stock(): void
    {
        $item = $this->item('Must remain', 5);
        try { app(InventoryCountReset::class)->reset($this->admin, 'RESET SELECTED STOCK', 'Empty selection', []); $this->fail('Empty selection accepted'); }
        catch (ValidationException) { $this->assertEquals(5, $item->stock()->sum('quantity')); }
    }
    public function test_streamers_and_read_only_admins_cannot_reset_counts(): void
    {
        $this->item('Must remain', 5);
        $streamer = User::factory()->create();
        $streamer->assignRole(Role::findOrCreate('streamer', 'web'));
        $this->assertFalse(InventoryCountReset::canReset($streamer));
        NavVisibility::setReadOnlyForRole('admin', [InventoryItemResource::class]);
        $this->assertFalse(InventoryCountReset::canReset($this->admin));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InventoryCountReset::class)->reset($this->admin, 'RESET ALL STOCK', 'Not allowed');
    }
    public function test_reset_failure_rolls_back_stock_and_movements_together(): void
    {
        $item = $this->item('Must remain', 5);
        $this->mock(\App\Services\InventoryLotService::class, fn ($mock) => $mock->shouldReceive('consume')->andThrow(new \RuntimeException('Test failure')));
        try { app(InventoryCountReset::class)->reset($this->admin, 'RESET ALL STOCK', 'Test rollback'); $this->fail('Expected failure'); }
        catch (\RuntimeException $e) { $this->assertSame('Test failure', $e->getMessage()); }
        $this->assertEquals(5, $item->stock()->sum('quantity'));
        $this->assertDatabaseCount('inventory_movements', 0);
    }
}

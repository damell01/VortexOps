<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ManageStock;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\Streamer;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\InventoryVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Moving stock, on a page that shows you what you are deciding against.
 *
 * These were three modals — adjust, transfer, send to my inventory — and each
 * asked for a number while covering the figures the number depends on. They are
 * one page now because they are one act: stock at one place becoming stock at
 * another, or a different amount of it. Three screens for that means three
 * places for the same mistake to be made differently.
 */
class ManageStockPageTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $item;
    private InventoryLocation $main;
    private InventoryLocation $back;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        Setting::set('enabled_admin_modules', json_encode(['inventory']));
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);

        $this->main = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $this->back = InventoryLocation::create(['name' => 'Back Room', 'type' => 'main_storage', 'status' => 'active']);

        $this->item = InventoryItem::create([
            'name' => 'Chrome Box', 'sku' => 'CHR-1',
            'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->main->id,
            'quantity'              => 20,
        ]);

        cache()->forget('inv_loc:active');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    private function page(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(ManageStock::class, ['record' => $this->item->id]);
    }

    public function test_it_opens_on_the_location_the_stock_is_actually_in(): void
    {
        // With one location that is the whole question answered; with several
        // it is still the likeliest.
        $this->page()->assertSet('fromLocationId', $this->main->id);
    }

    public function test_the_quantity_box_starts_at_what_is_already_there(): void
    {
        // So leaving it alone means "no change" rather than "set it to zero",
        // which is the difference between a form you can open safely and one
        // you cannot.
        $this->page()->assertSet('newQuantity', '20');
    }

    public function test_it_says_what_it_will_do_before_it_does_it(): void
    {
        $effect = $this->page()
            ->set('newQuantity', '14')
            ->instance()
            ->effect;

        $this->assertStringContainsString('Remove 6', $effect);
        $this->assertStringContainsString('20 becomes 14', $effect);
    }

    public function test_an_adjustment_is_written_with_its_reason(): void
    {
        $this->page()
            ->set('newQuantity', '14')
            ->set('reason', 'six were water damaged')
            ->call('submit');

        $movement = InventoryMovement::latest('id')->first();

        $this->assertSame('adjustment', $movement->movement_type);
        $this->assertEqualsWithDelta(-6.0, $movement->signedChange(), 0.01);
        $this->assertSame('six were water damaged', $movement->reason);
    }

    public function test_an_adjustment_without_a_reason_is_refused(): void
    {
        // A number nobody can explain later is worse than no number.
        $before = InventoryMovement::count();

        $this->page()
            ->set('newQuantity', '14')
            ->set('reason', '')
            ->call('submit');

        $this->assertSame($before, InventoryMovement::count());
        $this->assertEqualsWithDelta(20.0, $this->stockAt($this->main), 0.01);
    }

    public function test_a_transfer_moves_it_and_records_both_ends(): void
    {
        $this->page()
            ->call('setOperation', ManageStock::TRANSFER)
            ->set('toLocationId', $this->back->id)
            ->set('moveQuantity', '5')
            ->call('submit');

        $movement = InventoryMovement::latest('id')->first();

        $this->assertSame('transfer', $movement->movement_type);
        $this->assertSame($this->main->id, $movement->from_location_id);
        $this->assertSame($this->back->id, $movement->to_location_id);

        $this->assertEqualsWithDelta(15.0, $this->stockAt($this->main), 0.01);
        $this->assertEqualsWithDelta(5.0, $this->stockAt($this->back), 0.01);
    }

    public function test_the_source_is_not_offered_as_its_own_destination(): void
    {
        // Moving stock from a place to itself is not a transfer, and offering
        // it invites a movement that nets to nothing and still lands in the
        // audit trail.
        $destinations = $this->page()
            ->call('setOperation', ManageStock::TRANSFER)
            ->instance()
            ->destinationOptions;

        $this->assertArrayNotHasKey($this->main->id, $destinations);
        $this->assertArrayHasKey($this->back->id, $destinations);
    }

    public function test_a_transfer_of_more_than_is_there_is_refused(): void
    {
        $this->page()
            ->call('setOperation', ManageStock::TRANSFER)
            ->set('toLocationId', $this->back->id)
            ->set('moveQuantity', '999')
            ->call('submit');

        // Nothing moved, and nothing went negative.
        $this->assertEqualsWithDelta(20.0, $this->stockAt($this->main), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->stockAt($this->back), 0.01);
    }

    public function test_a_streamer_can_only_send_to_their_own_inventory(): void
    {
        $streamerUser = User::factory()->create();
        $streamerUser->assignRole('streamer');

        $streamer = Streamer::create(['name' => 'Me', 'user_id' => $streamerUser->id, 'status' => 'active']);
        $mine     = InventoryLocation::create([
            'name' => 'My Shelf', 'type' => 'streamer_inventory',
            'status' => 'active', 'streamer_id' => $streamer->id,
        ]);
        cache()->forget('inv_loc:active');

        $this->actingAs($streamerUser);

        $page = Livewire::test(ManageStock::class, ['record' => $this->item->id]);

        // It opens on the operation that is theirs, and the only place they can
        // send anything is their own shelf.
        $page->assertSet('operation', ManageStock::SEND);
        $this->assertSame([$mine->id => 'My Shelf'], $page->instance()->destinationOptions);
    }

    private function stockAt(InventoryLocation $location): float
    {
        return (float) (InventoryStock::where('inventory_item_id', $this->item->id)
            ->where('inventory_location_id', $location->id)
            ->value('quantity') ?? 0);
    }
}

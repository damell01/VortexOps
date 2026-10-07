<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\User;
use App\Services\InventoryItemDeleter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Deleting an inventory item is one tap from its card, its row or its page;
 * stock on hand has to be written off on purpose (and is logged); and a
 * deleted item can be restored.
 */
class InventoryItemDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();
        $this->owner = User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($this->owner);
        $this->location = InventoryLocation::create(['name' => 'A Main Warehouse', 'status' => 'active']);
    }

    private function item(string $name, float $qty = 0): InventoryItem
    {
        $item = InventoryItem::create(['name' => $name, 'average_cost' => 10, 'is_active' => true, 'is_container' => false]);
        if ($qty > 0) InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $this->location->id, 'quantity' => $qty]);

        return $item;
    }

    public function test_an_item_without_stock_is_deleted_from_its_card(): void
    {
        $item = $this->item('Empty Box');

        Livewire::test(ListInventoryItems::class)
            ->mountAction('deleteItem', ['product' => $item->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted($item);
    }

    public function test_an_item_with_stock_needs_the_write_off_switched_on(): void
    {
        $item = $this->item('Booster Box', 5);

        Livewire::test(ListInventoryItems::class)
            ->mountAction('deleteItem', ['product' => $item->id])
            ->callMountedAction()
            ->assertHasActionErrors(['write_off' => 'accepted']);

        $this->assertNotSoftDeleted($item);
    }

    public function test_writing_off_zeroes_the_stock_logs_it_and_deletes(): void
    {
        $item = $this->item('Booster Box', 5);

        Livewire::test(ListInventoryItems::class)
            ->mountAction('deleteItem', ['product' => $item->id])
            ->setActionData(['write_off' => true, 'reason' => 'Duplicate'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSoftDeleted($item);
        $this->assertSame(0.0, (float) InventoryStock::where('inventory_item_id', $item->id)->sum('quantity'));
        $movement = InventoryMovement::where('inventory_item_id', $item->id)->latest('id')->first();
        $this->assertSame(5.0, (float) $movement->quantity);
        $this->assertStringContainsString('Duplicate', $movement->reason);
    }

    public function test_a_deleted_item_can_be_restored(): void
    {
        $item = $this->item('Oops');
        $deleter = app(InventoryItemDeleter::class);
        $deleter->delete($item);

        $deleter->restore($item->id);

        $this->assertNotSoftDeleted($item);
    }

    public function test_the_service_refuses_stock_without_write_off(): void
    {
        $this->expectException(\DomainException::class);
        app(InventoryItemDeleter::class)->delete($this->item('Held', 2));
    }

    public function test_streamers_cannot_delete(): void
    {
        Role::findOrCreate('streamer', 'web');
        $streamer = User::factory()->create();
        $streamer->assignRole('streamer');
        $this->actingAs($streamer);

        $this->assertFalse(\App\Filament\Resources\InventoryItemResource::canDeleteAny());
    }
}

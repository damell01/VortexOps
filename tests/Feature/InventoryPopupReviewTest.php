<?php
namespace Tests\Feature;

use App\Livewire\InventoryPriceAcknowledgment;
use App\Models\{InventoryLocation, InventoryStock, Product, User};
use App\Services\InventoryPriceAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class InventoryPopupReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::findOrCreate('streamer', 'web'));
        $this->actingAs($this->user);
        $this->location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $this->product = Product::create(['name' => 'Popup fixture', 'sku' => 'POPUP-1', 'sale_price' => 20]);
        InventoryStock::create(['inventory_item_id' => $this->product->id, 'inventory_location_id' => $this->location->id, 'quantity' => 2]);
    }

    public function test_acknowledging_the_displayed_batch_does_not_acknowledge_a_new_price_change(): void
    {
        $this->product->update(['sale_price' => 15]);
        $page = Livewire::test(InventoryPriceAcknowledgment::class);
        $shown = $page->get('changes');
        $this->assertCount(1, $shown);
        $this->product->update(['sale_price' => 12]);
        $page->call('acknowledge');
        $this->assertSame($shown[0]['id'], (int) DB::table('inventory_price_acknowledgments')->where('user_id', $this->user->id)->value('last_event_id'));
        $remaining = $page->get('changes');
        $this->assertCount(1, $remaining);
        $this->assertEquals(12, $remaining[0]['new_price']);
    }

    public function test_new_arrivals_remain_unread_when_the_open_popup_is_acknowledged(): void
    {
        $arrival = fn ($kind) => DB::table('inventory_arrival_events')->insertGetId(['product_id' => $this->product->id, 'location_id' => $this->location->id, 'kind' => $kind, 'quantity_before' => 0, 'quantity_after' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $first = $arrival('new_item');
        $page = Livewire::test(InventoryPriceAcknowledgment::class);
        $second = $arrival('restocked');
        $page->call('acknowledge');
        $this->assertDatabaseHas('inventory_arrival_reviews', ['user_id' => $this->user->id, 'event_id' => $first]);
        $this->assertDatabaseMissing('inventory_arrival_reviews', ['user_id' => $this->user->id, 'event_id' => $second]);
        $this->assertSame([$second], array_column($page->get('arrivals'), 'id'));
    }

    public function test_unacknowledged_changes_remain_pending_across_new_component_mounts(): void
    {
        $this->product->update(['sale_price' => 15]);
        $first = Livewire::test(InventoryPriceAcknowledgment::class)->get('changes');
        $this->assertSame($first, Livewire::test(InventoryPriceAcknowledgment::class)->get('changes'));
        Livewire::test(InventoryPriceAcknowledgment::class)->call('acknowledge');
        $this->assertSame([], app(InventoryPriceAlerts::class)->pending($this->user));
    }

    public function test_sold_out_feed_entries_are_not_labeled_as_new_arrivals(): void
    {
        DB::table('inventory_arrival_events')->insert(['product_id' => $this->product->id, 'location_id' => $this->location->id, 'kind' => 'sold_out', 'quantity_before' => 2, 'quantity_after' => 0, 'created_at' => now(), 'updated_at' => now()]);
        Livewire::test(\App\Livewire\InventoryArrivalFeed::class)->assertSee('Sold out')->assertDontSee('New arrival');
    }
}

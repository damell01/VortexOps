<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EndOfStreamForm;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The inventory picker holds a basket instead of committing one line a time.
 *
 * A report normally covers several items, and every "Add" closed the modal —
 * so filing one meant reopening the picker and re-typing the search for each
 * line. The basket stages quantities, shows a running total, and writes them
 * all on one action.
 *
 * Staging survives closing the picker on purpose: a mis-click on the backdrop
 * should not throw away a selection someone has just assembled.
 */
class EndOfStreamPickerStagesManyItemsTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('streamer', 'web');

        $this->user = User::factory()->create(['email' => 'streamer@example.com']);
        $this->user->assignRole('streamer');

        $streamer = Streamer::create([
            'user_id' => $this->user->id, 'name' => 'Tyler', 'email' => 'streamer@example.com',
            'status' => 'active', 'payout_type' => 'profit_share',
        ]);

        $channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'Break #51',
            'show_date'          => now()->subDay()->toDateString(),
            'status'             => 'mapping',
        ]);
        $this->show->streamers()->attach($streamer->id);

        $this->user = $this->user->fresh();
    }

    private function item(string $name, string $sku, float $cost): InventoryItem
    {
        $item = InventoryItem::create([
            'name' => $name, 'sku' => $sku,
            'unit_cost' => $cost, 'average_cost' => $cost, 'is_active' => true,
        ]);

        $location = InventoryLocation::firstOrCreate(['name' => 'Main'], ['is_active' => true]);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $location->id,
            'quantity'              => 25,
        ]);

        return $item;
    }

    private function page()
    {
        return Livewire::actingAs($this->user)
            ->test(EndOfStreamForm::class, ['showId' => (string) $this->show->id]);
    }

    public function test_staging_writes_nothing_until_it_is_added(): void
    {
        $first  = $this->item('Coke', 'EOS-1', 8.39);
        $second = $this->item('Pepsi', 'EOS-2', 4.00);

        $page = $this->page()
            ->call('stageItem', $first->id)
            ->call('stageItem', $first->id)
            ->call('stageItem', $second->id)
            ->assertOk();

        $entry = StreamerLogEntry::firstWhere('show_id', $this->show->id);

        $this->assertSame(0, $entry->items()->count(), 'staging should not touch the report');
        $this->assertSame(['items' => 2, 'units' => 3, 'cost' => 20.78], $page->instance()->stagedSummary);
    }

    public function test_the_whole_basket_lands_in_one_action(): void
    {
        $first  = $this->item('Coke', 'EOS-1', 8.39);
        $second = $this->item('Pepsi', 'EOS-2', 4.00);

        $page = $this->page()
            ->call('stageItem', $first->id, 2)
            ->call('stageItem', $second->id, 5)
            ->call('addStagedItems')
            ->assertOk();

        $entry = StreamerLogEntry::firstWhere('show_id', $this->show->id);
        $lines = $entry->items()->get()->keyBy('inventory_item_id');

        $this->assertSame(2, $lines->count());
        $this->assertEqualsWithDelta(2.0, (float) $lines[$first->id]->quantity, 0.01);
        $this->assertEqualsWithDelta(5.0, (float) $lines[$second->id]->quantity, 0.01);

        $this->assertSame([], $page->instance()->stagedQuantities, 'the basket should empty once committed');
        $this->assertFalse($page->instance()->showInventoryPicker, 'the picker should close once the basket is in');
    }

    public function test_dropping_to_zero_removes_it_from_the_basket(): void
    {
        $item = $this->item('Coke', 'EOS-1', 8.39);

        $page = $this->page()
            ->call('stageItem', $item->id)
            ->call('stageItem', $item->id, -1);

        $this->assertSame([], $page->instance()->stagedQuantities);
        $this->assertSame(0, $page->instance()->stagedSummary['items']);
    }

    public function test_a_quantity_cannot_go_negative(): void
    {
        $item = $this->item('Coke', 'EOS-1', 8.39);

        $page = $this->page()->call('setStagedQuantity', $item->id, -8);

        $this->assertSame([], $page->instance()->stagedQuantities);
    }

    public function test_closing_the_picker_keeps_the_basket(): void
    {
        // A click on the backdrop is easy to do by accident and expensive to
        // recover from if it empties a selection someone just built up.
        $item = $this->item('Coke', 'EOS-1', 8.39);

        $page = $this->page()
            ->call('toggleBrowse')
            ->call('stageItem', $item->id, 4)
            ->call('toggleBrowse');

        $this->assertSame([$item->id => 4], $page->instance()->stagedQuantities);
    }

    public function test_adding_one_item_no_longer_closes_the_picker(): void
    {
        // The whole complaint: every add shut the modal and cleared the search.
        $item = $this->item('Coke', 'EOS-1', 8.39);

        $page = $this->page()
            ->call('toggleBrowse')
            ->set('search', 'Cok')
            ->call('addLineItem', $item->id, 1, 'sold');

        $this->assertTrue($page->instance()->showInventoryPicker, 'the picker closed on a single add');
        $this->assertSame('Cok', $page->instance()->search, 'the search was thrown away');
    }

    public function test_staging_something_already_on_the_report_tops_it_up(): void
    {
        $item = $this->item('Coke', 'EOS-1', 8.39);

        $this->page()
            ->call('addLineItem', $item->id, 3, 'sold')
            ->call('stageItem', $item->id, 2)
            ->call('addStagedItems');

        $entry = StreamerLogEntry::firstWhere('show_id', $this->show->id);

        $this->assertSame(1, $entry->items()->count(), 'a second line was created instead of topping up');
        $this->assertEqualsWithDelta(5.0, (float) $entry->items()->first()->quantity, 0.01);
    }

    public function test_committing_an_empty_basket_does_nothing(): void
    {
        $page = $this->page()->call('addStagedItems')->assertOk();

        $entry = StreamerLogEntry::firstWhere('show_id', $this->show->id);

        $this->assertSame(0, $entry->items()->count());
        $this->assertFalse($page->instance()->showInventoryPicker);
    }
}

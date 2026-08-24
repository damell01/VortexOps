<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EndOfStreamForm;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The picker has to stay usable when the catalog is a thousand things.
 *
 * It drew a flat grid of the first sixty matches and said nothing about the
 * rest, so past sixty items the honest answer to "is it in here" was "scroll
 * and hope". A silent cut also reads as "we do not stock that" when it means
 * "narrow your search", and on screen the two are identical.
 */
class EndOfStreamCatalogScalesTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;

    private User $user;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('streamer', 'web');

        $this->user = User::factory()->create(['email' => 'streamer@example.com']);
        $this->user->assignRole('streamer');

        $streamer = Streamer::create([
            'user_id' => $this->user->id, 'name' => 'Tyler',
            'email' => 'streamer@example.com', 'status' => 'active', 'payout_type' => 'profit_share',
        ]);

        $this->location = InventoryLocation::create(['name' => "Tyler's Shelf", 'is_active' => true, 'streamer_id' => $streamer->id]);

        $this->show = Show::create([
            'whatnot_channel_id' => WhatnotChannel::create(['name' => 'Vortex', 'status' => 'active'])->id,
            'title' => 'Break #90', 'show_date' => now()->subDay()->toDateString(), 'status' => 'mapping',
        ]);
        $this->show->streamers()->attach($streamer->id);

        $this->user = $this->user->fresh();
    }

    private function stockCatalog(int $count): void
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = [
                'name' => sprintf('Item %04d', $i),
                'sku'  => sprintf('CAT-%04d', $i),
                'category' => $i % 2 === 0 ? 'Baseball' : 'Basketball',
                'unit_cost' => 5, 'average_cost' => 5, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        InventoryItem::insert($items);

        $stock = [];
        foreach (InventoryItem::pluck('id') as $id) {
            $stock[] = [
                'inventory_item_id' => $id,
                'inventory_location_id' => $this->location->id,
                'quantity' => 5, 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::table('inventory_stock')->insert($stock);
    }

    private function page()
    {
        return Livewire::actingAs($this->user)
            ->test(EndOfStreamForm::class, ['showId' => (string) $this->show->id]);
    }

    public function test_it_reports_the_real_total_not_just_what_it_drew(): void
    {
        $this->stockCatalog(1000);

        $page = $this->page();

        $this->assertSame(1000, $page->instance()->inventoryTotal);
        $this->assertSame(60, $page->instance()->inventory->count(), 'the first page should stay small');
    }

    public function test_show_more_extends_the_page(): void
    {
        $this->stockCatalog(200);

        $page = $this->page()->call('showMoreInventory');

        $this->assertSame(120, $page->instance()->inventory->count());
    }

    public function test_searching_starts_the_list_again(): void
    {
        // Otherwise a deep scroll then a new search shows an oddly long list
        // of the new thing.
        $this->stockCatalog(200);

        $page = $this->page()->call('showMoreInventory')->set('search', 'Item 01');

        $this->assertSame(60, $page->instance()->pickerLimit);
    }

    public function test_a_category_narrows_it(): void
    {
        $this->stockCatalog(100);

        $page = $this->page()->set('pickerCategory', 'Baseball');

        $this->assertSame(50, $page->instance()->inventoryTotal);
    }

    public function test_selected_only_shows_just_the_basket(): void
    {
        // Reviewing a selection should not mean scrolling past everything
        // that is not in it.
        $this->stockCatalog(300);

        $picked = InventoryItem::orderBy('name')->limit(3)->pluck('id')->all();

        $page = $this->page();
        foreach ($picked as $id) {
            $page->call('stageItem', $id, 2);
        }

        $page->set('pickerStagedOnly', true);

        $this->assertSame(3, $page->instance()->inventoryTotal);
        $this->assertEqualsCanonicalizing($picked, $page->instance()->inventory->pluck('id')->all());
    }

    public function test_selected_only_with_an_empty_basket_shows_nothing(): void
    {
        $this->stockCatalog(50);

        $page = $this->page()->set('pickerStagedOnly', true);

        $this->assertSame(0, $page->instance()->inventoryTotal);
    }

    public function test_a_thousand_items_does_not_mean_a_thousand_queries(): void
    {
        $this->stockCatalog(1000);

        DB::enableQueryLog();
        $this->page()->call('loadDetails');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(40, $count, "rendering the picker took {$count} queries");
    }
}

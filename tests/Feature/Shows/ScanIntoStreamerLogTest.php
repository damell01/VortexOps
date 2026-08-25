<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EndOfStreamForm;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Scanning a box into a show report, instead of typing enough of its name to
 * find it and then picking the right one out of the near-duplicates a card
 * catalogue is full of.
 */
class ScanIntoStreamerLogTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;
    private InventoryLocation $location;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        $channel = WhatnotChannel::create(['name' => 'Chan', 'status' => 'active']);

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'Break Night',
            'show_date'          => today()->toDateString(),
            'status'             => 'draft',
            'created_by'         => $this->admin->id,
        ]);

        $this->location = InventoryLocation::create(['name' => 'Main', 'is_active' => true]);
    }

    private function stockedItem(array $attributes = [], int $quantity = 5): Product
    {
        $product = Product::create(array_merge([
            'name'      => 'Booster Box',
            'barcode'   => '012345678905',
            'is_active' => true,
        ], $attributes));

        InventoryStock::create([
            'inventory_item_id'     => $product->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => $quantity,
        ]);

        return $product;
    }

    private function page(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(EndOfStreamForm::class, ['showId' => (string) $this->show->id]);
    }

    public function test_a_scanned_barcode_is_staged(): void
    {
        $item = $this->stockedItem();

        $this->page()
            ->call('scanIntoPicker', '012345678905')
            ->assertSet('stagedQuantities', [$item->id => 1])
            ->assertSet('showInventoryPicker', true);
    }

    public function test_the_scanned_item_is_shown_as_well_as_staged(): void
    {
        // "Show that item" was the ask: the list underneath narrows to it,
        // and clearing the box puts the whole catalogue back.
        $item = $this->stockedItem(['name' => 'Gem 1 Box']);

        $this->page()
            ->call('scanIntoPicker', '012345678905')
            ->assertSet('search', 'Gem 1 Box');
    }

    public function test_scanning_the_same_box_twice_counts_two(): void
    {
        $item = $this->stockedItem();

        $this->page()
            ->call('scanIntoPicker', '012345678905')
            ->call('scanIntoPicker', '012345678905')
            ->assertSet('stagedQuantities', [$item->id => 2]);
    }

    public function test_a_upc_or_sku_scans_as_well_as_a_barcode(): void
    {
        $item = $this->stockedItem(['barcode' => null, 'upc' => '111222333444']);

        $this->page()
            ->call('scanIntoPicker', '111222333444')
            ->assertSet('stagedQuantities', [$item->id => 1]);

        $bySku = $this->stockedItem(['name' => 'Other', 'barcode' => null, 'sku' => 'VB-TEST-1']);

        $this->page()
            ->call('scanIntoPicker', 'VB-TEST-1')
            ->assertSet('stagedQuantities', [$bySku->id => 1]);
    }

    public function test_an_unknown_code_stages_nothing(): void
    {
        $this->stockedItem();

        $this->page()
            ->call('scanIntoPicker', '999999999999')
            ->assertSet('stagedQuantities', []);
    }

    public function test_an_item_outside_the_reports_locations_cannot_be_scanned_in(): void
    {
        // Scoped the same way the picker is: an item the report cannot draw
        // on must not become a line just because it scanned. An admin filing
        // an unscoped report may pick anything, so the check only has teeth
        // once the show belongs to a streamer with locations of their own —
        // which is every report a streamer files.
        $streamer = \App\Models\Streamer::create([
            'name'     => 'Jordan',
            'user_id'  => $this->admin->id,
            'pay_type' => 'profit_share',
        ]);
        // hasMany, not a pivot: a location belongs to one streamer.
        $this->location->update(['streamer_id' => $streamer->id]);
        $this->show->streamers()->attach($streamer->id);

        $mine = $this->stockedItem(['name' => 'On My Shelf']);

        $elsewhere = Product::create([
            'name'      => 'Someone Elses Shelf',
            'barcode'   => '555555555555',
            'is_active' => true,
        ]);
        $other = InventoryLocation::create(['name' => 'Other', 'is_active' => true]);
        InventoryStock::create([
            'inventory_item_id'     => $elsewhere->id,
            'inventory_location_id' => $other->id,
            'quantity'              => 9,
        ]);

        $this->page()
            ->call('scanIntoPicker', '555555555555')
            ->assertSet('stagedQuantities', [])
            // The one that is on the report's own shelf still scans.
            ->call('scanIntoPicker', '012345678905')
            ->assertSet('stagedQuantities', [$mine->id => 1]);
    }

    public function test_an_empty_scan_does_nothing(): void
    {
        $this->stockedItem();

        $this->page()
            ->call('scanIntoPicker', '  ')
            ->assertSet('stagedQuantities', []);
    }

    public function test_the_search_box_matches_a_typed_barcode(): void
    {
        // A gun scanner aimed at the search field types the code and presses
        // Enter. Without barcode and UPC in the search it matched nothing and
        // the catalogue looked empty.
        $item = $this->stockedItem(['name' => 'Findable Box']);

        $component = $this->page()->set('search', '012345678905');

        $this->assertTrue(
            $component->instance()->inventory->contains(fn ($row) => $row->id === $item->id),
        );
    }

    public function test_the_whatnot_order_count_is_gone_from_the_report(): void
    {
        // Nothing on this page reconciles against it — the report records what
        // physically left the shelf, giveaways and promos included, which
        // Whatnot has no order for.
        $view = file_get_contents(resource_path('views/filament/pages/end-of-stream-items.blade.php'));

        $this->assertStringNotContainsString("['Orders',", $view);
        $this->assertStringContainsString("['Shipments',", $view);
    }
}

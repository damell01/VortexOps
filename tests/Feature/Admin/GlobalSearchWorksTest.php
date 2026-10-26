<?php

namespace Tests\Feature\Admin;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Pallet;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WhatnotChannel;
use Filament\Livewire\GlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The top-bar search runs every searchable section's query at once, so one
 * section that errors (on MySQL, say) leaves the whole box doing nothing.
 */
class GlobalSearchWorksTest extends TestCase
{
    use RefreshDatabase;

    public function test_searching_returns_results_from_every_section(): void
    {
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email'), 'name' => 'Topps Owner']));

        $channel = WhatnotChannel::create(['name' => 'Topps Channel', 'status' => 'active']);
        $vendor = Vendor::create(['name' => 'Topps Vendor', 'status' => 'active']);
        $location = InventoryLocation::create(['name' => 'Topps Shelf', 'type' => 'main_storage', 'status' => 'active']);
        $item = InventoryItem::create(['name' => 'Topps Chrome Box', 'sku' => 'TOPPS-1', 'is_active' => true]);
        InventoryMovement::create(['inventory_item_id' => $item->id, 'to_location_id' => $location->id, 'quantity' => 3, 'movement_type' => 'opening', 'reason' => 'Topps restock']);
        Pallet::create(['vendor_id' => $vendor->id, 'reference' => 'TOPPS-PO', 'status' => 'pending']);
        Streamer::create(['name' => 'Topps Streamer', 'status' => 'active']);
        Show::create(['whatnot_channel_id' => $channel->id, 'title' => 'Topps Night', 'show_date' => now()->toDateString(), 'status' => 'reconciled']);

        $results = Livewire::test(GlobalSearch::class)
            ->set('search', 'topps')
            ->assertOk()
            ->assertSee('Topps Chrome Box')
            ->instance()->getResults();

        $this->assertNotNull($results);
        $this->assertGreaterThan(3, count($results->getCategories()));
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryGuide;
use App\Models\InventoryLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The guide reads the install it documents, so its setup advice is either
 * accurate or visibly wrong — a page that always says "make sure a location
 * exists" is one the reader learns to skip.
 */
class InventoryGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );
    }

    public function test_it_renders(): void
    {
        Livewire::test(InventoryGuide::class)->assertOk();
    }

    public function test_it_warns_when_no_locations_exist_at_all(): void
    {
        Livewire::test(InventoryGuide::class)
            ->assertSee('You have no active locations');
    }

    public function test_it_names_the_missing_piece_when_storage_is_absent(): void
    {
        // The exact situation behind "I can't find a main warehouse": locations
        // exist, but they are all streamer shelves.
        InventoryLocation::create(['name' => 'Brandon', 'type' => 'streamer_inventory', 'status' => 'active']);

        Livewire::test(InventoryGuide::class)
            ->assertSee('No general storage location')
            ->assertSee('Brandon');
    }

    public function test_it_confirms_a_healthy_setup_rather_than_nagging(): void
    {
        InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);

        Livewire::test(InventoryGuide::class)
            ->assertSee('Locations are set up')
            ->assertDontSee('You have no active locations');
    }

    public function test_inactive_locations_do_not_count_as_set_up(): void
    {
        // They are hidden from every dropdown, so counting them would tell the
        // reader everything is fine while the screens stay empty.
        InventoryLocation::create(['name' => 'Old Shelf', 'type' => 'main_storage', 'status' => 'inactive']);

        Livewire::test(InventoryGuide::class)
            ->assertSee('You have no active locations');
    }

    public function test_every_tab_opens(): void
    {
        $page = Livewire::test(InventoryGuide::class);

        foreach (['start', 'add', 'restock', 'pallets', 'fix', 'trouble'] as $tab) {
            $page->call('setTab', $tab)->assertSet('tab', $tab)->assertOk();
        }
    }

    public function test_the_troubleshooting_tab_answers_the_warehouse_question(): void
    {
        Livewire::test(InventoryGuide::class)
            ->call('setTab', 'trouble')
            ->assertSee('cannot find a main warehouse');
    }
}

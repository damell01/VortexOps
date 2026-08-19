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

    /** Tab key => a phrase only that tab's content contains. */
    public static function tabs(): array
    {
        return [
            'Start Here'        => ['start',   'The six location types'],
            'Add & Edit Items'  => ['items',   'Create Inventory Item'],
            'Restock & Scan'    => ['restock', 'The scanning screens'],
            'Stage & Receive'   => ['pallets', 'Staging a pallet'],
            'Costs & Reports'   => ['costs',   'The reporting screens'],
            'Fixing Mistakes'   => ['fix',     'Reconciling a location'],
            'Troubleshooting'   => ['trouble', 'cannot find a main warehouse'],
        ];
    }

    /**
     * Every tab shows its own content.
     *
     * setTab() accepts any string, so asserting the property alone passes for a
     * tab whose section was renamed or removed — the page then renders a strip
     * of buttons and nothing underneath.
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tabs')]
    public function test_each_tab_renders_its_content(string $tab, string $phrase): void
    {
        Livewire::test(InventoryGuide::class)
            ->call('setTab', $tab)
            ->assertSet('tab', $tab)
            ->assertSee($phrase);
    }

    public function test_the_guide_is_about_using_the_module_not_its_internals(): void
    {
        // This guide is read by people receiving pallets, not by whoever
        // maintains the matching pipeline. Model names and inference backends
        // are not part of anyone's job on the packing bench.
        $html = Livewire::test(InventoryGuide::class)->html();

        foreach (['Ollama', 'embedding', 'nomic-embed', 'AI Matching'] as $jargon) {
            $this->assertStringNotContainsStringIgnoringCase($jargon, $html);
        }
    }

    public function test_the_troubleshooting_tab_answers_the_warehouse_question(): void
    {
        Livewire::test(InventoryGuide::class)
            ->call('setTab', 'trouble')
            ->assertSee('cannot find a main warehouse');
    }
}

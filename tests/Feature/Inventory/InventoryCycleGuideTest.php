<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The walkthrough that follows one box from the loading dock to the payout.
 *
 * Every other tab explains a screen; this one explains the order they happen
 * in, which is the thing a new person is actually missing.
 */
class InventoryCycleGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );
    }

    public function test_the_cycle_is_the_tab_you_land_on(): void
    {
        // "What happens to a box?" is the question someone new has. "What does
        // this screen do?" is the question they have on their second day.
        Livewire::test(InventoryGuide::class)
            ->assertSet('tab', 'cycle')
            ->assertSee('One box, from the loading dock to the payout');
    }

    public function test_it_walks_the_whole_cycle_in_order(): void
    {
        $page = Livewire::test(InventoryGuide::class);

        foreach ([
            'Have somewhere to put it',
            'Make sure the item exists in the catalogue',
            'Record what it costs and what it should fetch',
            'Book the delivery in against a pallet',
            'Scan it in',
            'Check it landed where you meant',
            'Send it out, and account for what left',
            'See what it was worth',
        ] as $step) {
            $page->assertSee($step);
        }
    }

    public function test_every_step_shows_the_screen_it_describes(): void
    {
        // A guide that describes a screen without showing it asks the reader
        // to picture somewhere they have never been, which is how people end
        // up following the words onto the wrong page.
        $html = Livewire::test(InventoryGuide::class)->html();

        foreach ([
            '01-locations.png', '02-item-list.png', '03-item-create.png', '04-pallets.png',
            '05-scanner.png', '06-stock.png', '07-movements.png', '08-value.png',
        ] as $shot) {
            $this->assertStringContainsString($shot, $html, "the walkthrough does not show {$shot}");
            $this->assertFileExists(public_path('guide/' . $shot), "{$shot} is referenced but not on disk");
        }
    }

    public function test_the_setup_warning_is_not_hidden_behind_a_tab(): void
    {
        // With no location nothing on any tab can be followed to the end, so
        // it is not one tab's business.
        Livewire::test(InventoryGuide::class)
            ->assertSet('tab', 'cycle')
            ->assertSee('You have no active locations');
    }

    public function test_the_cycle_tab_is_offered_to_everyone_who_can_open_the_guide(): void
    {
        // It documents the order of things rather than any one screen, so
        // there is nothing to gate it on — unlike the tabs that walk you to a
        // page your role may not have.
        $tabs = Livewire::test(InventoryGuide::class)->instance()->visibleTabs;

        $this->assertArrayHasKey('cycle', $tabs);
        $this->assertSame('cycle', array_key_first($tabs));
    }
}

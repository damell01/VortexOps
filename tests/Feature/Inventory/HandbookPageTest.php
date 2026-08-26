<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\Handbook;
use App\Models\User;
use App\Support\InventoryManual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The handbook on screen.
 *
 * It reads from the same class the printed one does, so what matters here is
 * not the wording — that is the PDF test's job — but that every way of getting
 * to a piece of it works: the contents, the search, the two back-of-the-book
 * pages, and the module switcher that is mostly promises so far.
 */
class HandbookPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The handbook is filtered by what you can open, so most of these tests
     * need someone who can open everything — otherwise they would be asserting
     * against whichever screens a bare user happens to reach today.
     */
    private function user(): User
    {
        return (User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh();
    }

    private function page(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->user())->test(Handbook::class);
    }

    public function test_it_opens_on_the_contents_rather_than_a_section(): void
    {
        $page = $this->page()->assertOk();

        $page->assertSet('section', null);

        // Every section is reachable from the first screen.
        foreach ($page->instance()->allSections() as $section) {
            $page->assertSee($section['title']);
        }
    }

    public function test_a_section_shows_its_steps_and_screenshots(): void
    {
        // Read the sections off the component rather than the content class:
        // the page shows what this person can open, and that is what should be
        // asserted against.
        $page     = $this->page();
        $sections = $page->instance()->allSections();
        $index    = array_search('The catalogue', array_column($sections, 'title'), true);

        $this->assertNotFalse($index, 'the catalogue section has been renamed');

        $page->call('openSection', $index)->assertOk();

        foreach ($sections[$index]['steps'] as $step) {
            $page->assertSee($step['title']);

            if ($step['shot']) {
                $page->assertSee(InventoryManual::IMAGE_DIR . '/' . $step['shot'], false);
            }
        }
    }

    public function test_the_field_reference_is_on_the_page_not_only_in_the_pdf(): void
    {
        // The reason this page exists: someone in a form wanting to know what
        // one field does, without leaving the app to open a PDF.
        $this->page()
            ->set('search', 'Reorder Level')
            ->assertSee('Reorder Level')
            ->assertSee('Low Stock');
    }

    public function test_search_looks_at_step_text_screens_and_warnings(): void
    {
        $page = $this->page()->set('search', 'weighted average');

        $this->assertGreaterThan(0, $page->instance()->searchResultCount);

        $page->set('search', 'quicksilver zeppelin');

        $this->assertSame(0, $page->instance()->searchResultCount);
        $page->assertSee('Nothing matches');
    }

    public function test_searching_a_symptom_finds_the_page_written_to_answer_it(): void
    {
        // The whole reason someone types into a handbook is that something is
        // wrong in front of them. A search that skips the troubleshooting page
        // is a search that misses the one page written for that moment.
        $page = $this->page()->set('search', 'scan finds nothing');

        $this->assertNotEmpty($page->instance()->matchedTroubleshooting());
        $page->assertSee('That code is on no item', false);
    }

    public function test_searching_a_screen_name_finds_it_in_the_index(): void
    {
        $page = $this->page()->set('search', 'barcode printer');

        $this->assertNotEmpty($page->instance()->matchedScreens());
        $page->assertSee('Every screen');
    }

    public function test_the_result_count_covers_all_three_kinds_of_page(): void
    {
        $page     = $this->page()->set('search', 'barcode');
        $instance = $page->instance();

        $this->assertSame(
            $instance->searchStepCount
                + count($instance->matchedTroubleshooting())
                + count($instance->matchedScreens()),
            $instance->searchResultCount,
        );

        // And the three are genuinely different numbers, not one counted thrice.
        $this->assertGreaterThan($instance->searchStepCount, $instance->searchResultCount);
    }

    public function test_opening_a_section_clears_a_search(): void
    {
        // Otherwise the section you just clicked renders filtered down to the
        // steps that matched, which reads as a section with pieces missing.
        $this->page()
            ->set('search', 'pallet')
            ->call('openSection', 0)
            ->assertSet('search', '')
            ->assertSet('section', 0);
    }

    public function test_an_open_section_offers_a_jump_to_every_step_in_it(): void
    {
        // A fourteen-step section with a picture on each is a long scroll to
        // reach step eleven, and a longer one to find your place again after
        // you looked away at the screen it describes.
        $page     = $this->page();
        $sections = $page->instance()->allSections();
        $index    = array_search('The catalogue', array_column($sections, 'title'), true);

        $page->call('openSection', $index)->assertSee('In this section');

        foreach ($sections[$index]['steps'] as $n => $step) {
            // The anchor, and the step it lands on.
            $page->assertSee('#step-' . ($n + 1), false);
            $page->assertSee('id="step-' . ($n + 1) . '"', false);
        }
    }

    public function test_the_jump_list_is_not_offered_where_there_is_nothing_to_jump_to(): void
    {
        $this->page()->assertDontSee('In this section');

        $this->page()
            ->call('openSection', Handbook::TROUBLESHOOTING)
            ->assertDontSee('In this section');
    }

    public function test_the_back_pages_render(): void
    {
        $this->page()
            ->call('openSection', Handbook::TROUBLESHOOTING)
            ->assertSee('When something looks wrong')
            ->assertSee('A scan finds nothing');

        $this->page()
            ->call('openSection', Handbook::SCREEN_INDEX)
            ->assertSee('Every screen, and what it is for')
            ->assertSee('Barcode Printer');
    }

    public function test_reading_forwards_walks_into_the_back_pages(): void
    {
        $page = $this->page();
        $last = count($page->instance()->allSections()) - 1;

        $this->assertSame(
            [Handbook::TROUBLESHOOTING, 'When something looks wrong'],
            $page->set('section', $last)->instance()->neighbour(1),
        );

        $this->assertNull($page->set('section', 0)->instance()->neighbour(-1));
    }

    public function test_a_module_with_no_handbook_cannot_be_opened(): void
    {
        // The tab is shown so people know it is coming; selecting it would
        // otherwise leave them on an empty page wondering what broke.
        $this->page()
            ->call('selectModule', 'payouts')
            ->assertSet('module', 'inventory');
    }

    public function test_it_offers_the_printed_version(): void
    {
        $this->page()->assertSee(route('export.inventory-manual-pdf'), false);
    }
}

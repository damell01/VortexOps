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

    private function user(): User
    {
        return User::factory()->create();
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
        foreach (InventoryManual::sections() as $section) {
            $page->assertSee($section['title']);
        }
    }

    public function test_a_section_shows_its_steps_and_screenshots(): void
    {
        $sections = InventoryManual::sections();
        $index    = array_search('The catalogue', array_column($sections, 'title'), true);

        $this->assertNotFalse($index, 'the catalogue section has been renamed');

        $page = $this->page()->call('openSection', $index)->assertOk();

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
        $last = count(InventoryManual::sections()) - 1;

        $page = $this->page();

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

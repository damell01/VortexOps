<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\Handbook;
use App\Filament\Pages\HandbookFlipbook;
use App\Models\User;
use App\Support\HandbookFlipbook as Book;
use App\Support\HandbookVisibility;
use App\Support\InventoryManual;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The handbook as a book you turn the pages of.
 *
 * Built from the same content as the Handbook screen and the PDF, so what is
 * asserted here is the binding: every step lands on a page exactly once, the
 * contents point at the right pages, and the leaves pair up.
 */
class HandbookFlipbookTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return (User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh();
    }

    public function test_the_book_opens_on_a_cover_and_closes_on_a_back_page_of_whole_leaves(): void
    {
        $this->actingAs($this->owner());

        $pages = Book::pages(InventoryManual::class);

        $this->assertSame('cover', $pages[0]['kind']);
        $this->assertSame('contents', $pages[1]['kind']);
        $this->assertSame('back', end($pages)['kind']);

        // Two faces to a leaf, and the back cover is the back of the last one.
        $this->assertSame(0, count($pages) % 2);
    }

    public function test_every_visible_step_is_on_exactly_one_page_in_order(): void
    {
        $this->actingAs($this->owner());

        $expected = [];
        foreach (HandbookVisibility::sections(InventoryManual::sections()) as $section) {
            foreach ($section['steps'] as $step) {
                $expected[] = $step['title'];
            }
        }

        $onPages = array_map(
            fn (array $page) => $page['step']['title'],
            array_values(array_filter(Book::pages(InventoryManual::class), fn (array $page) => $page['kind'] === 'step')),
        );

        $this->assertNotEmpty($expected);
        $this->assertSame($expected, $onPages);
    }

    public function test_the_contents_point_at_the_page_each_part_starts_on(): void
    {
        $this->actingAs($this->owner());

        $pages = Book::pages(InventoryManual::class);

        foreach ($pages[1]['entries'] as $entry) {
            $page = $pages[$entry['page']];

            if ($page['kind'] === 'section') {
                $this->assertStringEndsWith($page['title'], $entry['label']);
            } else {
                $this->assertContains($page['kind'], ['troubleshooting', 'screens']);
                $this->assertSame($entry['label'], $page['title']);
            }
        }
    }

    public function test_the_back_of_the_book_tables_keep_every_row(): void
    {
        $this->actingAs($this->owner());

        $pages = Book::pages(InventoryManual::class);
        $rows  = fn (string $kind) => array_merge(...array_map(
            fn (array $page) => array_column($page['rows'], 0),
            array_values(array_filter($pages, fn (array $page) => $page['kind'] === $kind)),
        ));

        $this->assertSame(array_column(HandbookVisibility::troubleshooting(InventoryManual::troubleshooting()), 0), $rows('troubleshooting'));
        $this->assertSame(array_column(HandbookVisibility::screens(InventoryManual::screenIndex()), 0), $rows('screens'));
    }

    public function test_the_page_renders_every_step_and_its_screenshot(): void
    {
        $page = Livewire::actingAs($this->owner())->test(HandbookFlipbook::class)->assertOk();

        foreach (Book::pages(InventoryManual::class) as $p) {
            if ($p['kind'] !== 'step') {
                continue;
            }

            $page->assertSee($p['step']['title']);

            foreach ($p['shots'] as $shot) {
                $page->assertSee(InventoryManual::IMAGE_DIR . '/' . $shot, false);
            }
        }
    }

    public function test_the_handbook_offers_the_book(): void
    {
        Livewire::actingAs($this->owner())->test(Handbook::class)
            ->assertSee('Read as a book')
            ->assertSee(HandbookFlipbook::getUrl(), false);
    }

    public function test_whoever_cannot_open_the_handbook_cannot_open_the_book(): void
    {
        Role::findOrCreate('warehouse', 'web');
        $user = User::factory()->create(['email' => 'warehouse@example.com']);
        $user->assignRole('warehouse');

        NavVisibility::setVisibleForRole('warehouse', [\App\Filament\Resources\PalletResource::class]);
        NavVisibility::flushMemo();
        $this->actingAs($user->fresh());

        $this->assertFalse(Handbook::canAccess());
        $this->assertFalse(HandbookFlipbook::canAccess());

        NavVisibility::setVisibleForRole('warehouse', [Handbook::class]);
        NavVisibility::flushMemo();

        $this->assertTrue(HandbookFlipbook::canAccess());
    }
}

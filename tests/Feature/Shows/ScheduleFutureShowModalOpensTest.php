<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\ShowResource\Pages\ListShows;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Schedule Future Show" has to actually open.
 *
 * It mounted correctly the whole time — Livewire returned the form, the action
 * state was right, nothing threw — and the modal still never appeared, because
 * of where its markup landed rather than what it contained.
 *
 * Filament skips the page-level <x-filament-actions::modals /> for any page
 * implementing HasTable and lets the table render it instead (see
 * filament/filament/resources/views/components/page/index.blade.php). ListShows
 * uses a custom Blade view, and that view had {{ $this->table }} inside a
 * collapsed <details>, so the only modal container on the page lived in content
 * the browser does not render. Every modal action on the page was affected, not
 * just this one.
 *
 * These pin the parts a PHP test can see: the modal container is present in the
 * page's own output, no <details> wraps the table, and the action completes.
 * The visual half — that the modal is painted where the viewport can see it —
 * is guarded by EntranceAnimationsDoNotPersistTransformTest.
 */
class ScheduleFutureShowModalOpensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->admin);
    }

    public function test_the_action_modal_container_is_rendered_on_the_page(): void
    {
        // Every mounted action's modal is rendered into this one container,
        // and on a HasTable page the table is what renders it — loadTable()
        // first because the table defers its own render. That the container
        // arrives with the table is exactly why the table's position in this
        // view decides whether any modal on the page can be seen.
        Livewire::test(ListShows::class)
            ->call('loadTable')
            ->assertOk()
            ->assertSeeHtml('wire:partial="action-modals"');
    }

    public function test_the_table_is_not_buried_in_a_details_element(): void
    {
        // <details> is the specific trap: its contents stay in the DOM, so
        // everything server-side looks healthy, and the browser renders none of
        // it while the element is closed. Any collapse on this page has to keep
        // the subtree rendered — max-height, not display.
        $view = file_get_contents(resource_path(
            'views/filament/resources/show-resource/pages/list-shows.blade.php'
        ));

        // Blade comments are stripped first — the note explaining this trap
        // names the element it warns about.
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $view);

        $this->assertStringContainsString('{{ $this->table }}', $markup, 'the table is no longer rendered by this view');
        $this->assertDoesNotMatchRegularExpression('/<details\b/', $markup, 'the table must not sit inside a <details> — its action modals render with it');
    }

    public function test_scheduling_a_show_creates_it(): void
    {
        Livewire::test(ListShows::class)
            ->callAction('schedule_show', [
                'show_date' => now()->addWeek()->toDateString(),
                'title'     => 'Upcoming Break #50',
            ])
            ->assertHasNoActionErrors();

        $show = Show::firstWhere('title', 'Upcoming Break #50');

        $this->assertNotNull($show);
        $this->assertSame('draft', $show->status);
        $this->assertSame('manual', $show->import_source);
    }

    public function test_a_show_scheduled_without_a_title_still_gets_one(): void
    {
        // The field is optional. A show saved with a null title lists as a
        // blank row and reads as "'' is set for ..." in its own confirmation.
        Livewire::test(ListShows::class)
            ->callAction('schedule_show', ['show_date' => '2026-12-05'])
            ->assertHasNoActionErrors();

        $show = Show::latest('id')->first();

        $this->assertNotNull($show);
        $this->assertSame('Show on Dec 05, 2026', $show->title);
    }
}

<?php

namespace Tests\Feature\Shows;

use App\Livewire\StreamerHome;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The streamer's home: next show, numbers, and every report as a card. */
class StreamerHomeTest extends TestCase
{
    use RefreshDatabase;

    private Streamer $streamer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Trey Card', 'timezone' => 'UTC']);
        $user->assignRole('streamer');
        $this->streamer = Streamer::create(['name' => 'Trey Card', 'status' => 'active', 'user_id' => $user->id]);
        $this->actingAs($user->fresh());
    }

    protected function show(string $title, string $date, ?string $status = null, array $entry = []): Show
    {
        $show = Show::create(['title' => $title, 'show_date' => $date, 'status' => 'reconciled', 'gross_revenue' => 100]);
        $show->streamers()->attach($this->streamer->id, ['is_primary' => true]);
        if ($status) {
            StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'status' => $status] + $entry);
        }

        return $show;
    }

    /** A card, rather than the title anywhere — the clock's show picker lists titles too. */
    private function card(Show $show): string
    {
        return 'wire:key="vsh-show-' . $show->id . '"';
    }

    public function test_reports_sort_into_their_tabs(): void
    {
        $unlogged = $this->show('Unlogged Show', now()->subDay()->toDateString());
        $sentBack = $this->show('Sent Back Show', now()->subDays(2)->toDateString(), 'changes_requested', ['submitted_at' => now()]);
        $filed = $this->show('Filed Show', now()->subDays(3)->toDateString(), 'streamer_reviewed', ['submitted_at' => now()]);

        $page = Livewire::test(StreamerHome::class)
            ->assertSee('Welcome back, Trey!')
            ->assertSeeHtml($this->card($unlogged))->assertSee('Not started')->assertSee('Log sold items')
            ->assertDontSeeHtml($this->card($filed));

        $page->call('setTab', 'changes')->assertSeeHtml($this->card($sentBack))->assertSee('Fix report')->assertDontSeeHtml($this->card($unlogged));
        $page->call('setTab', 'submitted')->assertSeeHtml($this->card($filed))->assertSee('View report')->assertDontSeeHtml($this->card($unlogged));
        $page->call('setTab', 'all')->assertSeeHtml($this->card($unlogged))->assertSeeHtml($this->card($sentBack))->assertSeeHtml($this->card($filed));
    }

    public function test_cards_page_six_at_a_time(): void
    {
        $shows = collect(range(1, 8))->map(fn ($i) => $this->show("Paged Show {$i}", now()->subDays($i)->toDateString()));

        Livewire::test(StreamerHome::class)
            ->assertSee('Showing 1–6 of 8 shows')
            ->assertSeeHtml($this->card($shows[0]))->assertDontSeeHtml($this->card($shows[7]))
            ->call('nextPage')
            ->assertSeeHtml($this->card($shows[7]))->assertDontSeeHtml($this->card($shows[0]));
    }

    public function test_the_next_show_counts_down(): void
    {
        $show = $this->show('Tomorrow Night Breaks', now()->addDay()->toDateString());
        $show->update(['start_time' => now()->addDay()->setTime(19, 0)]);

        Livewire::test(StreamerHome::class)
            ->assertSee('Next show')
            ->assertSee('Tomorrow Night Breaks')
            ->assertSee('vsh-count', false);
    }
}

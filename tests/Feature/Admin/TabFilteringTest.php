<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ShowResource\Pages\ListShows;
use App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every list-page "tab" (Needs Review, This Week, To Review, etc.) is
 * implemented via Tab::make(...)->modifyQueryUsing(fn (Builder $q) => ...).
 * Filament's evaluate() only injects the query by the named key 'query' —
 * a closure parameter named anything else (e.g. $q) falls through to
 * app()->make(Builder::class), which builds a disconnected Eloquent Builder
 * with no model attached. Switching to any non-default tab then 500'd the
 * instant the table tried to render its (always-present) summary row —
 * "Call to a member function newQueryWithoutRelationships() on null".
 * These tests click through every tab on both resources that define one,
 * and separately verify each tab's modifyQuery() actually narrows results
 * (checked directly against the query, not the rendered table, since the
 * status filter dropdown always lists every status label regardless of
 * which tab is active).
 */
class TabFilteringTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
    }

    public function test_every_show_tab_renders_without_error(): void
    {
        $this->actingAs($this->owner());
        $creator = User::factory()->create();

        Show::create(['title' => 'Reconciled', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        Show::create(['title' => 'Pending', 'show_date' => now()->toDateString(), 'status' => 'pending_review', 'created_by' => $creator->id]);

        foreach (['past_7_days', 'all', 'needs_review', 'this_week', 'unreconciled'] as $tabKey) {
            Livewire::test(ListShows::class)->set('activeTab', $tabKey)->assertOk();
        }
    }

    public function test_show_tabs_actually_narrow_the_query(): void
    {
        $creator = User::factory()->create();
        Show::create(['title' => 'Reconciled', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        Show::create(['title' => 'Pending', 'show_date' => now()->toDateString(), 'status' => 'pending_review', 'created_by' => $creator->id]);

        $tabs = (new ListShows())->getTabs();

        $this->assertSame(1, $tabs['needs_review']->modifyQuery(Show::query())->count());
        $this->assertSame(1, $tabs['unreconciled']->modifyQuery(Show::query())->count());
        $this->assertSame(2, $tabs['all']->modifyQuery(Show::query())->count());
    }

    public function test_past_7_days_is_the_default_tab_and_scopes_to_the_recent_window(): void
    {
        $creator = User::factory()->create();
        Show::create(['title' => 'Yesterday', 'show_date' => now()->subDay()->toDateString(), 'status' => 'pending_review', 'created_by' => $creator->id]);
        Show::create(['title' => 'Ten Days Ago', 'show_date' => now()->subDays(10)->toDateString(), 'status' => 'pending_review', 'created_by' => $creator->id]);
        Show::create(['title' => 'Next Month', 'show_date' => now()->addMonth()->toDateString(), 'status' => 'draft', 'created_by' => $creator->id]);

        $this->assertSame('past_7_days', (new ListShows())->getDefaultActiveTab());

        $tab = (new ListShows())->getTabs()['past_7_days'];
        $titles = $tab->modifyQuery(Show::query())->pluck('title')->all();
        $this->assertSame(['Yesterday'], $titles, 'the default view is only shows that streamed in the last 7 days');
    }

    public function test_every_streamer_log_tab_renders_without_error(): void
    {
        $this->actingAs($this->owner());
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $creator = User::factory()->create();
        $showA = Show::create(['title' => 'A', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        $showB = Show::create(['title' => 'B', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);

        StreamerLogEntry::create(['show_id' => $showA->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);
        StreamerLogEntry::create(['show_id' => $showB->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved']);

        foreach (['all', 'to_review', 'reviewed', 'approved'] as $tabKey) {
            Livewire::test(ListStreamerLogEntries::class)->set('activeTab', $tabKey)->assertOk();
        }
    }

    public function test_streamer_log_tabs_actually_narrow_the_query(): void
    {
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $creator = User::factory()->create();
        $showA = Show::create(['title' => 'A', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        $showB = Show::create(['title' => 'B', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);

        StreamerLogEntry::create(['show_id' => $showA->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);
        StreamerLogEntry::create(['show_id' => $showB->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved']);

        $tabs = (new ListStreamerLogEntries())->getTabs();

        $this->assertSame(1, $tabs['to_review']->modifyQuery(StreamerLogEntry::query())->count());
        $this->assertSame(1, $tabs['approved']->modifyQuery(StreamerLogEntry::query())->count());
        $this->assertSame(2, $tabs['all']->modifyQuery(StreamerLogEntry::query())->count());
    }
}

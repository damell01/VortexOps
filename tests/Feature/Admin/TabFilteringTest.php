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

        foreach (array_keys((new ListShows())->getTabs()) as $tabKey) {
            Livewire::test(ListShows::class)->set('activeTab', $tabKey)->assertOk();
        }
    }

    public function test_show_tabs_actually_narrow_the_query(): void
    {
        $creator = User::factory()->create();
        Show::create(['title' => 'Reconciled', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        Show::create(['title' => 'Pending', 'show_date' => now()->toDateString(), 'status' => 'pending_review', 'created_by' => $creator->id]);

        $tabs = (new ListShows())->getTabs();

        $this->assertSame(2, $tabs['needs_matching']->modifyQuery(Show::query())->count());
        $this->assertSame(2, $tabs['needs_matching']->modifyQuery(Show::query())->count());
        $this->assertSame(2, $tabs['all']->modifyQuery(Show::query())->count());
    }

    public function test_default_tab_shows_operational_work_and_excludes_historical_imports(): void
    {
        $creator = User::factory()->create();
        $active = Show::create(['title' => 'Operational', 'show_date' => today(), 'is_operational' => true, 'created_by' => $creator->id]);
        $historical = Show::create(['title' => 'Historical', 'import_source' => 'auto_whatnot', 'show_date' => today()->subDays(10), 'is_operational' => false, 'created_by' => $creator->id]);
        $historical->forceFill(['is_operational' => false])->save();
        $page = new ListShows();
        $this->assertSame('all', $page->getDefaultActiveTab());
        $this->assertSame([$active->id], $page->getTabs()['all']->modifyQuery(Show::query())->pluck('id')->all());
    }

    public function test_every_streamer_log_tab_renders_without_error(): void
    {
        $this->actingAs($this->owner());
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $creator = User::factory()->create();
        $showA = Show::create(['title' => 'A', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        $showB = Show::create(['title' => 'B', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);

        StreamerLogEntry::create(['show_id' => $showA->id, 'streamer_id' => $streamer->id, 'status' => 'pending', 'submitted_at' => null]);
        StreamerLogEntry::create(['show_id' => $showB->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved', 'approval_status' => 'approved']);

        // Taken from the page rather than written out: a hardcoded list went
        // stale when the tabs were renamed, and setting an activeTab that does
        // not exist still renders fine, so the test kept passing while
        // covering nothing.
        foreach (array_keys((new ListStreamerLogEntries())->getTabs()) as $tabKey) {
            Livewire::test(ListStreamerLogEntries::class)->set('activeTab', $tabKey)->assertOk();
        }
    }

    public function test_streamer_log_tabs_actually_narrow_the_query(): void
    {
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $creator = User::factory()->create();
        $showA = Show::create(['title' => 'A', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        $showB = Show::create(['title' => 'B', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);

        StreamerLogEntry::create(['show_id' => $showA->id, 'streamer_id' => $streamer->id, 'status' => 'pending', 'submitted_at' => null]);
        StreamerLogEntry::create(['show_id' => $showB->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved', 'approval_status' => 'approved']);

        $tabs = (new ListStreamerLogEntries())->getTabs();

        // 'in_progress' is what 'to_review' was renamed to: started, not yet
        // submitted for review.
        $this->assertSame(1, $tabs['in_progress']->modifyQuery(StreamerLogEntry::query())->count());
        $this->assertSame(1, $tabs['approved']->modifyQuery(StreamerLogEntry::query())->count());
        $this->assertSame(2, $tabs['all']->modifyQuery(StreamerLogEntry::query())->count());
    }
}

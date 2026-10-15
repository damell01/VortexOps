<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The inbox tabs narrow the inbox; they never widen it. A report the counts
 * leave out (before the report start date) is not listed under a tab either.
 */
class InboxTabsKeepScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_needs_attention_lists_only_what_it_counts(): void
    {
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
        Setting::set('show_reports_required_from', now()->subDays(3)->toDateString());

        $streamer = Streamer::create(['name' => 'Scope Streamer', 'status' => 'active']);
        $old = Show::create(['title' => 'Before The Start', 'show_date' => now()->subDays(10)->toDateString()]);
        $new = Show::create(['title' => 'After The Start', 'show_date' => now()->subDay()->toDateString()]);
        $before = StreamerLogEntry::create(['show_id' => $old->id, 'streamer_id' => $streamer->id, 'status' => 'changes_requested']);
        $after = StreamerLogEntry::create(['show_id' => $new->id, 'streamer_id' => $streamer->id, 'status' => 'changes_requested']);

        $page = Livewire::test(ListStreamerLogEntries::class)->set('activeTab', 'attention');

        $listed = (fn () => $this->getFilteredSortedTableQuery())->call($page->instance())->pluck('id')->all();

        $this->assertSame([$after->id], $listed);
        $this->assertNotContains($before->id, $listed);
        $this->assertSame(1, $page->instance()->tabCounts()['attention']);
    }
}

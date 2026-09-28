<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\ShowDataAudit;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The audit's job is telling three different kinds of "no analytics" apart:
 * a show Whatnot never served an analytics tab for (it did not air, nothing
 * to chase), a show nobody has checked yet (the real backlog), and a show
 * partway through (some fields landed, some did not). Before the status
 * filter existed, all three sat in one flat, 50-row-capped list — indistinguishable
 * and, past 50, invisible.
 */
class ShowDataAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'dbellcreations@gmail.com']);
    }

    private function show(string $title, array $attrs = []): Show
    {
        return Show::create(array_merge([
            'title' => $title,
            'show_date' => today()->subDays(3)->toDateString(),
            'created_by' => 1,
        ], $attrs));
    }

    public function test_a_never_aired_show_and_an_unchecked_show_are_filtered_separately(): void
    {
        $this->actingAs($this->admin());

        $this->show('Confirmed no-show', [
            'analytics_sync_status' => 'unavailable',
            'analytics_unavailable_at' => now(),
        ]);

        $this->show('Never checked yet');

        $page = Livewire::test(ShowDataAudit::class);

        $page->call('setStatusFilter', 'unavailable');
        $onlyUnavailable = $page->instance()->getAuditData()['missing'];
        $this->assertCount(1, $onlyUnavailable);
        $this->assertSame('Confirmed no-show', $onlyUnavailable->first()->title);

        $page->call('setStatusFilter', 'unclassified');
        $onlyUnchecked = $page->instance()->getAuditData()['missing'];
        $this->assertCount(1, $onlyUnchecked);
        $this->assertSame('Never checked yet', $onlyUnchecked->first()->title);
    }

    public function test_a_complete_show_never_appears_in_follow_up_regardless_of_filter(): void
    {
        $this->actingAs($this->admin());

        $show = $this->show('Fully synced show', [
            'analytics_sync_status' => 'complete',
            'gross_revenue' => 500,
            'whatnot_net' => 400,
            'completed_earnings' => 400,
            'show_duration' => 90,
        ]);
        // last_analytics_synced_at is not mass-assignable (see Show::$fillable).
        $show->forceFill(['last_analytics_synced_at' => now()])->saveQuietly();

        $data = Livewire::test(ShowDataAudit::class)->instance()->getAuditData();

        $this->assertSame(0, $data['followUpTotal']);
        $this->assertCount(0, $data['missing']);
        $this->assertSame(1, $data['statusCounts']['complete']);
    }

    public function test_the_follow_up_list_paginates_past_the_per_page_limit(): void
    {
        $this->actingAs($this->admin());

        foreach (range(1, 55) as $i) {
            $this->show("Unchecked show {$i}");
        }

        $page = Livewire::test(ShowDataAudit::class);

        $firstPage = $page->instance()->getAuditData();
        $this->assertSame(55, $firstPage['followUpTotal']);
        $this->assertSame(2, $firstPage['followUpPages']);
        $this->assertCount(50, $firstPage['missing']);

        $page->call('goToFollowUpPage', 2);
        $secondPage = $page->instance()->getAuditData();
        $this->assertCount(5, $secondPage['missing']);

        $titlesSeen = $firstPage['missing']->pluck('id')->merge($secondPage['missing']->pluck('id'))->unique();
        $this->assertCount(55, $titlesSeen, 'every unchecked show should appear exactly once across both pages');
    }

    public function test_changing_the_status_filter_resets_back_to_page_one(): void
    {
        $this->actingAs($this->admin());

        foreach (range(1, 55) as $i) {
            $this->show("Unchecked show {$i}");
        }

        $page = Livewire::test(ShowDataAudit::class);
        $page->call('goToFollowUpPage', 2);
        $this->assertSame(2, $page->instance()->getAuditData()['followUpPage']);

        $page->call('setStatusFilter', 'unclassified');
        $this->assertSame(1, $page->instance()->getAuditData()['followUpPage']);
    }
}

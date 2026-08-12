<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ShowStatusBoard;
use App\Models\Show;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusBoardAgingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        $this->actingAs(User::factory()->create());
    }

    private function boardShow(int $showId): ?Show
    {
        $columns = (new ShowStatusBoard)->getColumns();

        foreach ($columns as $column) {
            if ($found = $column['shows']->firstWhere('id', $showId)) {
                return $found;
            }
        }

        return null;
    }

    public function test_status_change_stamps_status_changed_at(): void
    {
        $show = Show::create([
            'title' => 'Aging Show', 'show_date' => now()->toDateString(),
            'status' => 'pending_review', 'created_by' => auth()->id(),
        ]);

        // Backdate creation, then transition — the stamp should move to "now".
        $show->update(['status_changed_at' => now()->subDays(10)]);
        $show->update(['status' => 'mapping']);

        $this->assertEqualsWithDelta(now()->timestamp, $show->fresh()->status_changed_at->timestamp, 5);
    }

    public function test_days_in_status_reflects_the_status_stamp(): void
    {
        $show = Show::create([
            'title' => 'Stuck Show', 'show_date' => now()->toDateString(),
            'status' => 'pending_review', 'created_by' => auth()->id(),
        ]);

        // It's been in pending_review for 5 days.
        $show->update(['status_changed_at' => now()->subDays(5)]);

        $boardShow = $this->boardShow($show->id);

        $this->assertNotNull($boardShow);
        $this->assertEquals(5, $boardShow->days_in_status);
    }

    public function test_days_in_status_falls_back_when_stamp_missing(): void
    {
        $show = Show::create([
            'title' => 'Legacy Show', 'show_date' => now()->toDateString(),
            'status' => 'pending_review', 'created_by' => auth()->id(),
        ]);

        // Simulate a pre-migration row: no stamp, aged updated_at.
        Show::where('id', $show->id)->update([
            'status_changed_at' => null,
            'updated_at'        => now()->subDays(3),
        ]);

        $boardShow = $this->boardShow($show->id);

        $this->assertNotNull($boardShow);
        $this->assertEquals(3, $boardShow->days_in_status);
    }

    /**
     * "Reconciled" is a terminal status — every finished show lands there
     * and stays forever, so without a window it grows without bound and the
     * board keeps getting taller. Recently-reconciled shows still appear;
     * anything older is summarized as a count with a link to the full list
     * instead of an ever-growing card list.
     */
    public function test_reconciled_column_only_shows_recent_completions(): void
    {
        $recent = Show::create([
            'title' => 'Recent', 'show_date' => now()->toDateString(),
            'status' => 'reconciled', 'created_by' => auth()->id(),
        ]);
        $recent->update(['status_changed_at' => now()->subDays(2)]);

        $old = Show::create([
            'title' => 'Old', 'show_date' => now()->subMonths(2)->toDateString(),
            'status' => 'reconciled', 'created_by' => auth()->id(),
        ]);
        $old->update(['status_changed_at' => now()->subDays(30)]);

        $columns = (new ShowStatusBoard)->getColumns();
        $reconciledColumn = collect($columns)->firstWhere('status', 'reconciled');

        $this->assertTrue($reconciledColumn['shows']->contains('id', $recent->id));
        $this->assertFalse($reconciledColumn['shows']->contains('id', $old->id));
        $this->assertSame(1, $reconciledColumn['hiddenOlder']);
    }

    public function test_reconciled_column_has_no_hidden_count_when_nothing_is_older(): void
    {
        $show = Show::create([
            'title' => 'Recent', 'show_date' => now()->toDateString(),
            'status' => 'reconciled', 'created_by' => auth()->id(),
        ]);
        $show->update(['status_changed_at' => now()->subDay()]);

        $columns = (new ShowStatusBoard)->getColumns();
        $reconciledColumn = collect($columns)->firstWhere('status', 'reconciled');

        $this->assertSame(0, $reconciledColumn['hiddenOlder']);
    }
}

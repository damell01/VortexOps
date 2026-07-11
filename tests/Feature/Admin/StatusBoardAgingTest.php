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
}

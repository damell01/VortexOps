<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ShowResource\Pages\ListShows;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The scraper imports upcoming/scheduled shows alongside completed ones, but
 * day-to-day work happens on shows that already streamed — so the Shows list
 * hides future-dated shows by default via a default-active toggle filter.
 */
class HideUpcomingShowsFilterTest extends TestCase
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

    public function test_upcoming_shows_are_hidden_by_default(): void
    {
        $this->actingAs($this->owner());
        $creator = User::factory()->create();

        $past = Show::create([
            'title' => 'Streamed Yesterday', 'show_date' => now()->subDay()->toDateString(),
            'status' => 'pending_review', 'created_by' => $creator->id,
        ]);
        $today = Show::create([
            'title' => 'Streamed Today', 'show_date' => now()->toDateString(),
            'status' => 'pending_review', 'created_by' => $creator->id,
        ]);
        $upcoming = Show::create([
            'title' => 'Scheduled Next Week', 'show_date' => now()->addWeek()->toDateString(),
            'status' => 'draft', 'created_by' => $creator->id,
        ]);

        Livewire::test(ListShows::class)
            ->loadTable()
            ->assertOk()
            ->assertCanSeeTableRecords([$past, $today])
            ->assertCanNotSeeTableRecords([$upcoming]);
    }

    public function test_removing_the_filter_reveals_upcoming_shows(): void
    {
        $this->actingAs($this->owner());
        $creator = User::factory()->create();

        $upcoming = Show::create([
            'title' => 'Scheduled Next Week', 'show_date' => now()->addWeek()->toDateString(),
            'status' => 'draft', 'created_by' => $creator->id,
        ]);

        Livewire::test(ListShows::class)
            ->loadTable()
            ->removeTableFilter('hide_upcoming')
            ->assertOk()
            ->assertCanSeeTableRecords([$upcoming]);
    }
}

<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EndOfStreamForm;
use App\Filament\Pages\DashboardImproved;
use App\Livewire\StreamerHome;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Shows before the report start date are the streamer's history: listed, open
 * to log if they want, never counted as waiting on them.
 */
class PreviousShowsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Show $old;

    private Show $current;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->assignRole('streamer');
        $streamer = Streamer::create(['name' => 'History Streamer', 'status' => 'active', 'user_id' => $this->user->id]);

        Setting::set('show_reports_required_from', now()->subDays(5)->toDateString());
        $this->old = Show::create(['title' => 'Old Way Show', 'show_date' => now()->subDays(20)->toDateString(), 'status' => 'reconciled']);
        $this->current = Show::create(['title' => 'New Way Show', 'show_date' => now()->subDay()->toDateString(), 'status' => 'reconciled']);
        $this->old->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->current->streamers()->attach($streamer->id, ['is_primary' => true]);

        $this->actingAs($this->user->fresh());
    }

    public function test_an_older_show_is_listed_as_previous_and_not_waiting_on_the_streamer(): void
    {
        $page = Livewire::test(StreamerHome::class);
        $this->assertSame([$this->current->id], collect($page->instance()->reports()->items())->pluck('id')->all());
        $this->assertSame('1', DashboardImproved::getNavigationBadge());

        $page->call('setTab', 'all')->assertSee('Old Way Show')->assertSee('Previous show')->assertSee('View show');
    }

    public function test_the_streamer_can_still_open_an_older_show_to_log_it(): void
    {
        Livewire::test(EndOfStreamForm::class, ['showId' => (string) $this->old->id])
            ->assertOk()
            ->assertSee('Previous show')
            ->assertSee('You can still log items here');
    }
}

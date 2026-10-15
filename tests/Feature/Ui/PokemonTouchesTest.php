<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use App\Support\Buddy;
use App\Support\Season;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/** Seasonal visitors, shiny twins, the countdown and the receiving Poké Ball. */
class PokemonTouchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_halloween_is_october_only(): void
    {
        $this->assertSame('halloween', Season::current(CarbonImmutable::create(2026, 10, 1)));
        $this->assertSame('halloween', Season::current(CarbonImmutable::create(2026, 10, 31, 23)));
        $this->assertNull(Season::current(CarbonImmutable::create(2026, 11, 1)));
        $this->assertNull(Season::current(CarbonImmutable::create(2026, 9, 30)));
    }

    public function test_single_days_win_over_months_and_every_season_has_its_sprite(): void
    {
        $this->assertSame('winter', Season::current(CarbonImmutable::create(2026, 12, 14)));
        $this->assertSame('new-year', Season::current(CarbonImmutable::create(2027, 1, 1, 9)));
        $this->assertNull(Season::current(CarbonImmutable::create(2027, 1, 2)));
        $this->assertSame('pokemon-day', Season::current(CarbonImmutable::create(2027, 2, 27)));

        foreach (Season::SEASONS as $season) {
            $this->assertFileExists(public_path("img/pokemon/{$season['mon']}.gif"));
        }
    }

    public function test_the_holidays_land_on_the_right_days(): void
    {
        $this->assertSame('valentines', Season::current(CarbonImmutable::create(2027, 2, 14)));
        $this->assertSame('st-patricks', Season::current(CarbonImmutable::create(2027, 3, 17)));
        $this->assertSame('july-4', Season::current(CarbonImmutable::create(2027, 7, 4)));
        $this->assertNull(Season::current(CarbonImmutable::create(2027, 7, 5)));

        // Thanksgiving 2026 is Thursday Nov 26: the week runs Mon 23 to Sun 29.
        $this->assertNull(Season::current(CarbonImmutable::create(2026, 11, 22)));
        $this->assertSame('thanksgiving', Season::current(CarbonImmutable::create(2026, 11, 23)));
        $this->assertSame('thanksgiving', Season::current(CarbonImmutable::create(2026, 11, 29)));
        $this->assertNull(Season::current(CarbonImmutable::create(2026, 11, 30)));
    }

    public function test_every_sprite_has_a_shiny_twin(): void
    {
        foreach (glob(public_path('img/pokemon/*.gif')) as $sprite) {
            $this->assertFileExists(public_path('img/pokemon/shiny/'.basename($sprite)), basename($sprite).' has no shiny');
        }
    }

    public function test_a_streamer_sees_a_countdown_to_a_show_later_today(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-14 13:00:00');
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $user = User::factory()->create(['timezone' => 'UTC']);
        $user->assignRole('streamer');
        $streamer = \App\Models\Streamer::create(['name' => 'Tyler', 'status' => 'active', 'user_id' => $user->id]);
        $show = \App\Models\Show::create(['title' => 'Friday Break', 'show_date' => '2026-10-14', 'start_time' => '2026-10-14 15:15:00', 'status' => 'pending_review', 'created_by' => $user->id]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $this->assertSame('2026-10-14 15:15', $show->fresh()->startsAt('UTC')->format('Y-m-d H:i'));
        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('Your show starts in')->assertSee('vx-countdown', false);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_a_season_can_be_previewed_and_a_bad_preview_is_ignored(): void
    {
        config(['app.season' => 'winter']);
        $this->assertSame('winter', Season::current(CarbonImmutable::create(2026, 6, 1)));

        config(['app.season' => 'not-a-season']);
        $this->assertNull(Season::current(CarbonImmutable::create(2026, 6, 1)));
    }

    public function test_a_tip_renders_with_its_own_dismiss_key(): void
    {
        $html = Blade::render('<x-vx.tip id="pl-breakdown">Click any show.</x-vx.tip>');

        $this->assertStringContainsString('vx-tip-pl-breakdown', $html);
        $this->assertStringContainsString('Click any show.', $html);
        $this->assertStringContainsString('/img/pokemon/rotom.gif', $html);
    }

    public function test_every_buddy_has_a_sprite(): void
    {
        foreach (Buddy::CHOICES as $buddy) {
            $this->assertFileExists(public_path("img/pokemon/{$buddy}.gif"));
        }
    }

    public function test_the_receiving_ball_fills_and_is_caught_when_done(): void
    {
        $half = Blade::render('<x-vx.pokeball-progress :pct="50" />');
        $this->assertStringContainsString('y="16"', $half);
        $this->assertStringContainsString('50%', $half);
        $this->assertStringNotContainsString('is-caught', $half);

        $this->assertStringContainsString('is-caught', Blade::render('<x-vx.pokeball-progress :pct="100" :done="true" />'));
    }
}

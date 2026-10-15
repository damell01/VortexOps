<?php

namespace Tests\Feature\Ui;

use App\Livewire\ClockWidget;
use App\Models\User;
use App\Support\Buddy;
use App\Support\Season;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/** The seasonal Gengar, the time-clock buddy and the receiving Poké Ball. */
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

    public function test_rarity_follows_the_thresholds(): void
    {
        \App\Support\Rarity::flushMemo();
        $this->assertSame(0, \App\Support\Rarity::tier(null));
        $this->assertSame(0, \App\Support\Rarity::tier(49.99));
        $this->assertSame(1, \App\Support\Rarity::tier(50));
        $this->assertSame(2, \App\Support\Rarity::tier(150));
        $this->assertSame(3, \App\Support\Rarity::tier(1428));

        \App\Models\Setting::set(\App\Support\Rarity::SETTING, json_encode([1000, 10, 100]));
        \App\Support\Rarity::flushMemo();
        $this->assertSame(2, \App\Support\Rarity::tier(150), 'thresholds are sorted before use');
        \App\Models\Setting::set(\App\Support\Rarity::SETTING, '');
        \App\Support\Rarity::flushMemo();

        $this->assertStringContainsString('tier-3', Blade::render('<x-vx.rarity :cost="900" />'));
        $this->assertSame('', trim(Blade::render('<x-vx.rarity :cost="5" />')));
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

    public function test_picking_a_buddy_saves_it_and_shows_it_napping_while_clocked_out(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ClockWidget::class)
            ->assertSee('/img/pokemon/eevee.gif', false)
            ->assertSee('is-napping', false)
            ->call('chooseBuddy', 'charizard')
            ->assertSee('/img/pokemon/charizard.gif', false);

        $this->assertSame('charizard', $user->fresh()->buddy);
    }

    public function test_a_buddy_that_is_not_on_the_list_is_refused(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ClockWidget::class)->call('chooseBuddy', '../../etc/passwd');

        $this->assertNull($user->fresh()->buddy);
        $this->assertSame(Buddy::DEFAULT, Buddy::for($user->fresh()));
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

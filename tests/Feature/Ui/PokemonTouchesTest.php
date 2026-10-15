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

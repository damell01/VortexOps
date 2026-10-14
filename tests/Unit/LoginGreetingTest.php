<?php

namespace Tests\Unit;

use App\Support\LoginGreeting;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class LoginGreetingTest extends TestCase
{
    public function test_hours_fall_into_the_right_period(): void
    {
        $this->assertSame('late', LoginGreeting::period(2));
        $this->assertSame('late', LoginGreeting::period(4));
        $this->assertSame('morning', LoginGreeting::period(5));
        $this->assertSame('morning', LoginGreeting::period(11));
        $this->assertSame('afternoon', LoginGreeting::period(12));
        $this->assertSame('evening', LoginGreeting::period(17));
        $this->assertSame('evening', LoginGreeting::period(21));
        $this->assertSame('late', LoginGreeting::period(22));
    }

    public function test_late_night_is_gengar_or_a_dozing_snorlax(): void
    {
        foreach (range(1, 6) as $day) {
            $greet = LoginGreeting::for(CarbonImmutable::create(2026, 1, $day, 23, 30));

            $this->assertSame($greet['mon'] === 'Snorlax', $greet['sleepy']);
            $this->assertContains($greet['mon'], ['Gengar', 'Snorlax']);
            $this->assertSame('Late show tonight?', $greet['bubble']);
            $this->assertStringContainsString('late shift', $greet['caption']);
        }
    }

    public function test_every_sprite_it_can_pick_is_on_disk(): void
    {
        foreach ([3, 9, 14, 19] as $hour) {
            foreach (range(1, 10) as $day) {
                $greet = LoginGreeting::for(CarbonImmutable::create(2026, 3, $day, $hour));

                $this->assertFileExists(public_path(ltrim($greet['sprite'], '/')));
                $this->assertStringContainsString($greet['mon'], $greet['caption']);
            }
        }
    }

    public function test_the_morning_crew_rotates_by_day(): void
    {
        $mons = collect(range(1, 5))
            ->map(fn ($day) => LoginGreeting::for(CarbonImmutable::create(2026, 4, $day, 9))['mon'])
            ->unique();

        $this->assertCount(5, $mons);
    }
}

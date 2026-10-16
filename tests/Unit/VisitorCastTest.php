<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\VisitorCast;
use Tests\TestCase;

class VisitorCastTest extends TestCase
{
    public function test_chosen_buddy_takes_every_other_turn(): void
    {
        $user = new User(['buddy' => 'eevee']);
        $result = VisitorCast::for($user, ['top' => ['gengar', 'mew'], 'bottom' => ['snorlax']], 2);
        $this->assertSame(['eevee', 'gengar', 'eevee', 'mew'], $result['cast']['top']);
        $this->assertFalse($result['rare']);
    }

    public function test_rare_visitor_is_the_first_and_only_top_visitor_for_that_page(): void
    {
        $result = VisitorCast::for(new User(['buddy' => 'eevee']), ['top' => ['gengar'], 'bottom' => ['snorlax']], 1);
        $this->assertTrue($result['rare']);
        $this->assertCount(1, $result['cast']['top']);
        $this->assertContains($result['cast']['top'][0], ['mew', 'lugia', 'rayquaza']);
        $this->assertSame(['snorlax'], $result['cast']['bottom']);
    }

    public function test_buddy_off_preserves_regular_visitors(): void
    {
        $cast = ['top' => ['gengar'], 'bottom' => ['snorlax']];
        $this->assertSame($cast, VisitorCast::for(new User(['buddy' => 'none']), $cast, 2)['cast']);
    }
}

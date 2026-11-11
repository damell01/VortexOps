<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The sign-in page sits on a wall of Vortex-graded slabs; other simple pages do not. */
class LoginSlabWallTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sign_in_page_shows_the_slab_wall_and_the_password_reset_page_does_not(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertSee('class="vx-wall"', false)
            ->assertSee('GEM MT', false)
            ->assertSee('img/pokemon3d/gengar.gif', false)
            ->assertDontSee('vx-capture-scene', false);

        $this->get('/admin/password-reset/request')->assertOk()->assertDontSee('class="vx-wall"', false);
    }
}

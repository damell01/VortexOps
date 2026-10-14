<?php

namespace Tests\Feature\Ui;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Each error page has a Pokémon encounter, and the sprite it names is on disk. */
class ErrorPagesTest extends TestCase
{
    public function test_every_error_page_renders_its_pokemon(): void
    {
        foreach ([403, 404, 419, 500, 503] as $code) {
            $html = view("errors.$code", ['exception' => new HttpException($code, '')])->render();

            $this->assertMatchesRegularExpression('#src="/img/pokemon/([a-z]+)\.gif"#', $html, "errors.$code has no Pokémon");
            preg_match('#src="/img/pokemon/([a-z]+)\.gif"#', $html, $m);
            $this->assertFileExists(public_path("img/pokemon/{$m[1]}.gif"), "errors.$code points at a missing sprite");
            $this->assertStringContainsString('class="battle"', $html);
            $this->assertStringContainsString((string) $code, $html);
        }
    }

    public function test_an_unknown_page_shows_psyduck(): void
    {
        $this->get('/admin/no-such-page-anywhere')
            ->assertNotFound()
            ->assertSee('Psyduck is confused', false)
            ->assertSee('/img/pokemon/psyduck.gif', false);
    }

    public function test_the_403_page_still_says_which_rule_refused(): void
    {
        $html = view('errors.403', ['exception' => new HttpException(403, '')])->render();

        $this->assertStringContainsString('vx-403-details', $html);
        $this->assertStringContainsString('Meowth', $html);
    }
}

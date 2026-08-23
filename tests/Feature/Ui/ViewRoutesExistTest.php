<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every route() a view names must exist.
 *
 * The Streamer Hub linked to filament.admin.resources.streamer-logs.create,
 * which is not a route — the resource has no create page. route() throws on an
 * unknown name, so that one dead link took the entire page down, and only for
 * streamers, who are the only people who can open it. It sat there unnoticed
 * because nobody who could see the error had a reason to visit.
 *
 * Grepping the names out of the views and asking the router about each is
 * cheap, and catches the whole class: a renamed page, a deleted resource, a
 * typo in a link nobody clicks often.
 */
class ViewRoutesExistTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function routeReferences(): array
    {
        $root = dirname(__DIR__, 3) . '/resources/views';

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $found = [];

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            // Only literal names. A route built from a variable cannot be
            // checked here, and guessing at one would produce noise rather
            // than a finding.
            preg_match_all("/\broute\(\s*'([a-zA-Z0-9_.\-]+)'/", $source, $matches);

            // A name the file already guards with Route::has() is deliberately
            // optional — Laravel's own welcome page does this for login and
            // register, which are absent in an app with no public signup. It
            // is checked before use, so its absence is not a broken link.
            preg_match_all("/Route::has\(\s*'([a-zA-Z0-9_.\-]+)'/", $source, $guarded);
            $optional = array_flip($guarded[1]);

            foreach (array_unique($matches[1]) as $name) {
                if (isset($optional[$name])) {
                    continue;
                }

                $relative = str_replace($root . '/', '', $file->getPathname());
                $found["{$name} in {$relative}"] = [$name, $relative];
            }
        }

        return $found ?: ['no route() calls in any view' => ['', '']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routeReferences')]
    public function test_the_route_is_registered(string $name, string $view): void
    {
        if ($name === '') {
            $this->markTestSkipped('no literal route() calls found');
        }

        $this->assertTrue(
            Route::has($name),
            "{$view} links to route '{$name}', which does not exist — route() throws, so the page will not render",
        );
    }
}

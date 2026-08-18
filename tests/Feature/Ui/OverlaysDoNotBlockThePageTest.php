<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * A full-viewport overlay driven by x-show is invisible to Alpine until Alpine
 * initialises. Until then the markup is just a fixed, inset-0, high z-index div
 * sitting over the whole page, swallowing every click underneath it — the
 * sidebar toggle, tab strips, buttons, all of it.
 *
 * That is what "the menu button doesn't work on some pages" and "the tabs
 * won't let me click" were: two such overlays (the barcode scanner and the
 * global feedback widget) covering the viewport. Verified in a browser with
 * scripting disabled, which is the same DOM the user gets in the window before
 * Alpine boots, and permanently if it fails to boot at all.
 *
 * The fix is x-cloak (app.css defines [x-cloak] { display: none !important }),
 * or an inline display:none the way several elements in these files already do.
 * This checks nobody adds a third without one.
 */
class OverlaysDoNotBlockThePageTest extends TestCase
{
    public function test_no_full_viewport_overlay_is_visible_before_alpine_boots(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);

            foreach ($this->openingTags($source) as [$tag, $line]) {
                if (! str_contains($tag, 'x-show')) {
                    continue;
                }

                if (! $this->coversTheViewport($tag)) {
                    continue;
                }

                if ($this->isGuarded($tag)) {
                    continue;
                }

                $offenders[] = str_replace(base_path() . '/', '', $file) . ':' . $line;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Full-viewport x-show overlays with no x-cloak or display:none — these cover the "
            . "page and eat clicks until Alpine initialises:\n  " . implode("\n  ", $offenders),
        );
    }

    /** @return array<int, string> */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views')),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** @return array<int, array{0: string, 1: int}> opening tags with line numbers */
    private function openingTags(string $source): array
    {
        preg_match_all('/<(?:div|section)\b[^>]*>/s', $source, $matches, PREG_OFFSET_CAPTURE);

        return array_map(
            fn (array $m): array => [$m[0], substr_count(substr($source, 0, $m[1]), "\n") + 1],
            $matches[0],
        );
    }

    private function coversTheViewport(string $tag): bool
    {
        return (bool) preg_match('/class="[^"]*\bfixed\b[^"]*\binset-0\b/', $tag);
    }

    private function isGuarded(string $tag): bool
    {
        return str_contains($tag, 'x-cloak')
            || str_contains(str_replace(' ', '', $tag), 'display:none');
    }
}

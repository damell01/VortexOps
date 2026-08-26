<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * List markers belong to the list that asks for them, not to every <ul> and
 * <ol> in the app.
 *
 * responsive-typography-hierarchy.css used to carry `ul { list-style-type:
 * disc }` and `ol { list-style-type: decimal }`. It reads as typography and
 * behaves as a landmine, because almost nothing in a Filament app is a prose
 * list: the sidebar, breadcrumbs, global search results, the end-of-stream
 * step wizard and Filament's own `<ol class="fi-pagination-items">` are all
 * lists, and all of them grew markers.
 *
 * The visible damage was numbers appearing twice. Pagination read
 * "1. 1  2. 2  3. 3". The step wizard read "2. [2] Notes", and the marker on
 * step one vanished altogether because the rounded container clipped it — so
 * the row looked misnumbered as well as doubled.
 *
 * It was patched three times at the symptom — `list-style: none !important` on
 * the sidebar, then on global search — and each new list started the cycle
 * again. Tailwind's preflight already resets markers; a list that wants them
 * says so in its own class attribute.
 */
class ListMarkersAreOptedIntoTest extends TestCase
{
    public function test_no_stylesheet_gives_every_list_a_marker(): void
    {
        $offenders = [];

        foreach ($this->stylesheets() as $file) {
            foreach ($this->markerRules(file_get_contents($file)) as [$selector, $line]) {
                $offenders[] = str_replace(base_path() . '/', '', $file) . ':' . $line . '  ' . $selector;
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['A bare element selector is putting markers on every list in the app:', ''],
            $offenders,
            ['', 'Give the list that wants markers a list-disc / list-decimal class instead.'],
        )));
    }

    /**
     * Rules whose selector is nothing but bare ul/ol element names and which
     * set a marker. `.prose ul` or `ul.vx-checklist` are somebody being
     * specific and are left alone.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function markerRules(string $css): array
    {
        $found = [];

        // Selector list, then a block. Non-greedy so a block never swallows
        // the next rule.
        preg_match_all('/([^{}();@]+)\{([^{}]*)\}/', $css, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as [$whole, $selector, $body]) {
            if (! preg_match('/list-style(-type)?\s*:\s*(?!none)/i', $body[0])) {
                continue;
            }

            foreach (explode(',', $selector[0]) as $one) {
                $one = trim(preg_replace('/\s+/', ' ', $one));

                if ($one === '' || ! preg_match('/^(ul|ol|li)( (ul|ol|li))*$/i', $one)) {
                    continue;
                }

                $found[] = [$one, substr_count(substr($css, 0, $whole[1]), "\n") + 1];
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function stylesheets(): array
    {
        return array_values(array_filter(
            glob(resource_path('css/*.css')) ?: [],
            static fn (string $file) => is_readable($file),
        ));
    }
}

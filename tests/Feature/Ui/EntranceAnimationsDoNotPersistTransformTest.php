<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * An entrance animation must not leave a transform behind.
 *
 * `animation: vx-fade-up 220ms ease both` keeps the last keyframe applied for
 * as long as the element exists. When that keyframe sets `transform:
 * translateY(0)` the element ends up carrying an identity transform — no
 * visible effect at all, and enough to make it the containing block for every
 * `position: fixed` descendant.
 *
 * On `.fi-page-content` that silently relocated modals across the whole panel.
 * Filament renders `<x-filament-actions::modals />` inside the table for pages
 * implementing HasTable, i.e. inside `.fi-page-content`, so those modals were
 * laid out against the page body instead of the viewport and opened below the
 * fold — reachable only by scrolling, and on a long page not obviously there at
 * all. Nothing errored; the modal simply was not where anyone was looking.
 *
 * `backwards` gives the same entrance (it still applies the `from` keyframe
 * through any animation-delay, which the staggered cards rely on) and drops the
 * transform when the animation ends.
 */
class EntranceAnimationsDoNotPersistTransformTest extends TestCase
{
    /** @return array<int, string> */
    private function stylesheets(): array
    {
        return glob(resource_path('css/*.css')) ?: [];
    }

    /**
     * Names of @keyframes blocks whose final transform is the identity.
     *
     * Only those are a problem. An exit animation ending at `scale(.95)` or
     * `translateX(100%)` has a final state worth keeping, so `forwards` on it
     * is deliberate — and the element is on its way out anyway. An entrance
     * ending at `translateY(0)` keeps nothing anyone can see and costs the
     * page a stray containing block.
     *
     * @return array<int, string>
     */
    private function entranceKeyframes(string $css): array
    {
        preg_match_all('/@keyframes\s+([\w-]+)\s*\{((?:[^{}]|\{[^{}]*\})*)\}/', $css, $matches, PREG_SET_ORDER);

        $names = [];

        foreach ($matches as $match) {
            preg_match_all('/transform\s*:\s*([^;!}]+)/', $match[2], $transforms);

            if (empty($transforms[1])) {
                continue;
            }

            $final = trim(end($transforms[1]));

            if (preg_match('/^(none|translateY\(0\w*\)|translateX\(0\w*\)|translate\(0\w*(,\s*0\w*)?\)|scale\(1\)|translateZ\(0\w*\))$/', $final)) {
                $names[] = $match[1];
            }
        }

        return $names;
    }

    public function test_no_stylesheet_holds_a_transform_after_its_animation_ends(): void
    {
        $all = implode("\n", array_map(fn (string $f) => file_get_contents($f), $this->stylesheets()));
        $risky = $this->entranceKeyframes($all);

        $this->assertNotEmpty($risky, 'no entrance keyframes found — has the CSS moved?');

        $offenders = [];

        foreach ($this->stylesheets() as $file) {
            foreach (file($file) as $index => $line) {
                if (! preg_match('/animation\s*:\s*([^;]+);/', $line, $m)) {
                    continue;
                }

                $shorthand = $m[1];

                $usesTransform = array_filter($risky, fn (string $name) => preg_match('/\b' . preg_quote($name, '/') . '\b/', $shorthand));
                $persists = preg_match('/\b(both|forwards)\b/', $shorthand);

                if ($usesTransform && $persists) {
                    $offenders[] = basename($file) . ':' . ($index + 1) . ' — ' . trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['These animations leave a transform on the element forever, which makes it the'],
            ['containing block for any position: fixed descendant (modals, dropdowns, toasts).'],
            ['Use `backwards` instead of `both`/`forwards`:'],
            $offenders
        )));
    }

    public function test_the_page_content_entrance_is_the_one_that_matters_most(): void
    {
        // Named on its own because everything on every page sits inside it: if
        // this one regresses, every table-rendered modal in the panel moves.
        $css = file_get_contents(resource_path('css/app.css'));

        preg_match('/\.fi-page-content,\s*\.fi-simple-page\s*\{([^}]*)\}/', $css, $m);

        $this->assertNotEmpty($m, '.fi-page-content entrance rule is gone — check whether it moved or was renamed');
        $this->assertMatchesRegularExpression('/animation:[^;]*\bbackwards\b/', $m[1]);
    }
}

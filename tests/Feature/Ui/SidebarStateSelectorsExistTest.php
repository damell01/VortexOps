<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * The collapsed sidebar is styled off a class Filament actually sets.
 *
 * Our stylesheets carried twenty-three rules hung off
 * `.fi-sidebar[data-collapsed="true"]`. Filament emits no such attribute — it
 * binds `.fi-sidebar-open` from `$store.sidebar.isOpen`, so collapsed is the
 * absence of that class. Every one of those rules matched nothing, silently,
 * and CSS gives no warning for a selector that never fires.
 *
 * What that looked like: the rail collapsed to 68px because one rule did use
 * the right selector, but the labels it was supposed to hide stayed. "Streamer"
 * wrapped to a single letter per line down the rail and "Log out" was clipped
 * mid-word.
 *
 * This checks the class we depend on is still the one Filament ships, and that
 * nobody reintroduces the attribute that never existed.
 */
class SidebarStateSelectorsExistTest extends TestCase
{
    public function test_filament_still_marks_the_open_sidebar_with_the_class_we_style_against(): void
    {
        $sidebar = base_path('vendor/filament/filament/resources/views/livewire/sidebar.blade.php');

        if (! is_readable($sidebar)) {
            $this->markTestSkipped('Filament sidebar view not present.');
        }

        $this->assertStringContainsString(
            'fi-sidebar-open',
            file_get_contents($sidebar),
            'Filament no longer binds fi-sidebar-open. Every collapsed-rail rule in '
            . 'resources/css is hung off :not(.fi-sidebar-open) and has just stopped matching.',
        );
    }

    public function test_no_stylesheet_styles_the_sidebar_off_an_attribute_filament_does_not_set(): void
    {
        $offenders = [];

        foreach (glob(resource_path('css/*.css')) ?: [] as $file) {
            // Comments are where the trap is explained; blank them rather than
            // strip them so the reported line numbers still line up.
            $css = preg_replace_callback(
                '#/\*.*?\*/#s',
                static fn (array $m) => str_repeat("\n", substr_count($m[0], "\n")),
                file_get_contents($file),
            );

            if (! preg_match_all('/data-collapsed/', $css, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as [, $offset]) {
                $offenders[] = str_replace(base_path() . '/', '', $file)
                    . ':' . (substr_count(substr($css, 0, $offset), "\n") + 1);
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['data-collapsed is not an attribute Filament sets. These rules match nothing:', ''],
            $offenders,
            ['', 'Collapsed is .fi-sidebar:not(.fi-sidebar-open); expanded is .fi-sidebar.fi-sidebar-open.'],
        )));
    }
}

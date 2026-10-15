<?php

namespace Tests\Feature\Support;

use App\Support\HelpTopics;
use Tests\TestCase;

/** Every "?" in the app opens a topic that exists, and every topic says something. */
class HelpTopicsTest extends TestCase
{
    public function test_every_help_button_points_at_a_written_topic(): void
    {
        $files = array_merge(
            glob(base_path('app/{,*/,*/*/,*/*/*/,*/*/*/*/}*.php'), GLOB_BRACE),
            glob(base_path('resources/views/{,*/,*/*/,*/*/*/,*/*/*/*/}*.blade.php'), GLOB_BRACE),
        );
        $used = [];
        foreach ($files as $file) {
            $code = file_get_contents($file);
            preg_match_all("/HelpTopics::button\\('([a-z_]+\\.[a-z_.]+)'\\)|<x-vx-help topic=\"([a-z_]+\\.[a-z_.]+)\"/", $code, $m);
            $used = array_merge($used, array_filter($m[1]), array_filter($m[2]));
            // <x-vx-help :topic="cond ? 'a' : 'b'" />: every quoted topic in it.
            preg_match_all('/<x-vx-help :topic="([^"]+)"/', $code, $dynamic);
            foreach ($dynamic[1] as $expression) {
                preg_match_all("/'([a-z_]+\\.[a-z_.]+)'/", $expression, $quoted);
                $used = array_merge($used, $quoted[1]);
            }
        }
        $used = array_values(array_unique($used));

        $this->assertNotEmpty($used);
        foreach ($used as $topic) {
            $this->assertNotNull(HelpTopics::get($topic), "No help written for '{$topic}'");
        }
    }

    public function test_each_topic_has_a_title_an_intro_and_something_to_follow(): void
    {
        foreach (HelpTopics::all() as $key => $topic) {
            $this->assertNotSame('', trim($topic['title'] ?? ''), $key);
            $this->assertNotSame('', trim($topic['intro'] ?? ''), $key);
            $this->assertTrue(! empty($topic['steps']) || ! empty($topic['tips']), $key);
        }
    }

    public function test_the_button_renders_for_a_topic_and_nothing_for_an_unknown_one(): void
    {
        $this->assertStringContainsString('data-vx-help="locations.audience"', HelpTopics::button('locations.audience')->toHtml());
        $this->assertSame('', trim(HelpTopics::button('nope')->toHtml()));
    }
}

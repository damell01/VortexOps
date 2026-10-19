<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\HelpContent;
use App\Support\HelpTopics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every "?" in the app opens a topic that exists, and every topic says something. */
class HelpTopicsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_help_button_points_at_a_written_topic(): void
    {
        $files = [];
        foreach ([app_path(), resource_path('views')] as $directory) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) $files[] = $file->getPathname();
            }
        }
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
            $this->assertTrue(! empty($topic['steps']) || ! empty($topic['tips']) || ! empty($topic['sections']), $key);
        }
    }

    public function test_the_button_renders_for_a_topic_and_nothing_for_an_unknown_one(): void
    {
        $this->assertStringContainsString('data-vx-help="locations.audience"', HelpTopics::button('locations.audience')->toHtml());
        $this->assertSame('', trim(HelpTopics::button('nope')->toHtml()));
    }

    public function test_every_page_and_related_guide_points_at_written_help(): void
    {
        foreach (HelpContent::pageMap() as $class => $entry) {
            $this->assertTrue(class_exists($class), "{$class} does not exist");
            foreach ((array) $entry as $topic) {
                $this->assertNotNull(HelpTopics::get($topic), "{$class} points at missing help '{$topic}'");
            }
        }
        foreach (HelpTopics::all() as $key => $topic) {
            foreach ($topic['related'] ?? [] as $related) {
                $this->assertNotNull(HelpTopics::get($related), "{$key} links to missing help '{$related}'");
            }
            foreach ($topic['sections'] ?? [] as $section) {
                $this->assertNotEmpty($section['items'] ?? [], "{$key}: an empty section");
            }
        }
    }

    public function test_a_page_finds_its_help_by_page_then_resource_and_by_role(): void
    {
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));

        $this->assertSame('page.inbox', HelpTopics::forPage([\App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries::class, \App\Filament\Resources\StreamerLogResource::class]));
        $this->assertSame('page.locations', HelpTopics::forPage([\App\Filament\Resources\InventoryLocationResource\Pages\EditInventoryLocation::class, \App\Filament\Resources\InventoryLocationResource::class]));
        $this->assertSame('page.admin-dashboard', HelpTopics::forPage([\App\Filament\Pages\DashboardImproved::class]));
        $this->assertNull(HelpTopics::forPage([\App\Filament\Pages\LogViewer::class]));
    }

    public function test_the_words_load_for_a_signed_in_user_only(): void
    {
        $this->get(route('help.topics'))->assertRedirect();

        $topics = $this->actingAs(User::factory()->create())->getJson(route('help.topics'))->assertOk()->json();

        $this->assertSame('Setting up payroll', $topics['guide.payroll']['title']);
    }
}

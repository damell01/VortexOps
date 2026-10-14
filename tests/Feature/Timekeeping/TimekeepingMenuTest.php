<?php

namespace Tests\Feature\Timekeeping;

use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The desktop menu is a hand-written list: new pages have to be on it to be found. */
class TimekeepingMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_menu_has_one_timekeeping_link_and_the_grouped_reports(): void
    {
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));

        $html = view('filament.components.desktop-mega-nav')->render();

        // Timekeeping is one page (its dashboard opens Time & Attendance), so a plain link.
        $this->assertStringContainsString('href="'.\App\Filament\Pages\Timekeeping::getUrl().'"', $html);
        $this->assertStringNotContainsString('Clock in &amp; my time', $html);
        foreach (['Reports Overview', 'Whatnot Performance', 'Whatnot Losses', 'Business Profitability', 'Streamer Analytics', 'Financial', 'Operations'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }
}

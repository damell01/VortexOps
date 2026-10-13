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

    public function test_the_owner_menu_links_timekeeping_time_and_attendance_and_profit_and_loss(): void
    {
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));

        $html = view('filament.components.desktop-mega-nav')->render();

        $this->assertStringContainsString('Timekeeping', $html);
        $this->assertStringContainsString('Time &amp; Attendance', $html);
        $this->assertStringContainsString('Profit &amp; Loss', $html);
    }
}

<?php

namespace Tests\Feature\Notifications;

use App\Filament\Pages\NotificationSettings;
use App\Models\User;
use App\Services\NotificationRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The redesigned Notifications page: list switches, the Configure panel and schedules. */
class NotificationSettingsPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
    }

    public function test_a_link_opens_the_configure_panel(): void
    {
        Livewire::withQueryParams(['configure' => 'whatnot_negative'])->actingAs($this->owner)->test(NotificationSettings::class)
            ->assertSet('configure', 'whatnot_negative')
            ->assertSee('Negative Whatnot earnings')->assertSee('Skip empty summaries')->assertSee('Include businesses');
    }

    public function test_the_list_switch_saves_at_once(): void
    {
        Livewire::actingAs($this->owner)->test(NotificationSettings::class)->call('toggleEnabled', 'low_stock');

        $this->assertFalse(app(NotificationRouter::class)->rule('low_stock')['enabled']);
    }

    public function test_configure_saves_recipients_and_schedule_and_cancel_discards(): void
    {
        $person = User::factory()->create(['name' => 'Pat']);

        Livewire::actingAs($this->owner)->test(NotificationSettings::class)
            ->call('openConfigure', 'whatnot_negative')
            ->call('removeAudience', 'whatnot_negative', 'admins')
            ->call('addRecipient', 'whatnot_negative', 'u:'.$person->id)
            ->set('rules.whatnot_negative.schedule.mode', 'daily')
            ->set('rules.whatnot_negative.schedule.time', '07:30')
            ->assertSee('Changes not saved')
            ->call('saveConfigure')->assertSet('configure', null);

        $rule = app(NotificationRouter::class)->rule('whatnot_negative');
        $this->assertSame(['owner'], $rule['roles']);
        $this->assertSame([$person->id], $rule['users']);
        $this->assertSame('daily', $rule['schedule']['mode']);
        $this->assertSame('07:30', $rule['schedule']['time']);

        Livewire::actingAs($this->owner)->test(NotificationSettings::class)
            ->call('openConfigure', 'whatnot_negative')
            ->set('rules.whatnot_negative.schedule.mode', 'weekly')
            ->call('closeConfigure')
            ->assertSet('rules.whatnot_negative.schedule.mode', 'daily');
        $this->assertSame('daily', app(NotificationRouter::class)->rule('whatnot_negative')['schedule']['mode']);
    }

    public function test_tabs_and_search_filter_the_list(): void
    {
        $page = Livewire::actingAs($this->owner)->test(NotificationSettings::class)->set('group', 'System');
        $this->assertSame(['scraper_health_digest', 'analytics_overdue', 'system_health'], array_keys($page->instance()->visibleEvents()));

        $page->set('group', 'all')->set('search', 'whatnot earnings');
        $this->assertArrayHasKey('whatnot_negative', $page->instance()->visibleEvents());
    }
}
